import * as faceapi from 'face-api.js';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

const MODEL_URL = '/face-api-models';
const DETECTION_INTERVAL_MS = 150;
// A reading this far below the recent peak EAR counts as "eyes closed".
// Relative-to-peak rather than a fixed absolute EAR cutoff, since the tiny
// landmark model's eye-corner placement is less precise than the full
// dlib-style model the usual ~0.2 absolute threshold assumes - an absolute
// cutoff can sit on the wrong side of this model's actual open/closed range
// entirely, which is what was preventing blinks from ever registering.
const EAR_DROP_RATIO = 0.75;
const REQUIRED_BLINKS = 3;
const POSITIONING_TIMEOUT_MS = 12_000;
const BLINKING_TIMEOUT_MS = 30_000;
const FLASH_COLORS = ['red', 'green', 'blue'] as const;
const FLASH_DWELL_MS = 700;
const FLASH_BACKGROUND: Record<(typeof FLASH_COLORS)[number], string> = {
    red: '#dc2626',
    green: '#16a34a',
    blue: '#2563eb',
};

type CaptureStatus =
    | 'idle'
    | 'loading'
    | 'requesting'
    | 'positioning'
    | 'blinking'
    | 'flashing'
    | 'done'
    | 'error';

interface CaptureResult {
    selfieFrames: File[];
    flashFrames: File[];
    flashColors: string[];
}

interface LivenessCaptureProps {
    onCaptured: (result: CaptureResult) => void;
    onReset: () => void;
}

let modelsLoaded = false;

async function ensureModelsLoaded(): Promise<void> {
    if (modelsLoaded) {
        return;
    }

    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
    ]);
    modelsLoaded = true;
}

function distance(
    a: { x: number; y: number },
    b: { x: number; y: number },
): number {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

function eyeAspectRatio(eye: { x: number; y: number }[]): number {
    const [p1, p2, p3, p4, p5, p6] = eye;

    return (distance(p2, p6) + distance(p3, p5)) / (2 * distance(p1, p4));
}

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export function LivenessCapture({ onCaptured, onReset }: LivenessCaptureProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const captureCanvasRef = useRef<HTMLCanvasElement>(null);
    const overlayCanvasRef = useRef<HTMLCanvasElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const detectionTimerRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const detectionBusyRef = useRef(false);
    const eyeStateRef = useRef<'open' | 'closed'>('open');
    const peakEarRef = useRef(0);
    const selfieFramesRef = useRef<File[]>([]);
    const stoppedRef = useRef(false);

    const [status, setStatus] = useState<CaptureStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const [faceDetected, setFaceDetected] = useState(false);
    const [blinkCount, setBlinkCount] = useState(0);
    const [flashIndex, setFlashIndex] = useState<number | null>(null);

    function stopDetectionLoop() {
        if (detectionTimerRef.current !== null) {
            clearInterval(detectionTimerRef.current);
            detectionTimerRef.current = null;
        }

        const canvas = overlayCanvasRef.current;
        const ctx = canvas?.getContext('2d');

        if (canvas && ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }

    useEffect(() => {
        return () => {
            stoppedRef.current = true;
            streamRef.current?.getTracks().forEach((track) => track.stop());

            if (detectionTimerRef.current !== null) {
                clearInterval(detectionTimerRef.current);
            }
        };
        // Intentionally empty deps: unmount-only cleanup operating on refs.
    }, []);

    function fail(message: string) {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        stopDetectionLoop();
        setError(message);
        setStatus('error');
    }

    async function start() {
        setError(null);
        stoppedRef.current = false;
        selfieFramesRef.current = [];
        eyeStateRef.current = 'open';
        setBlinkCount(0);
        setFlashIndex(null);

        try {
            setStatus('loading');
            await ensureModelsLoaded();

            setStatus('requesting');
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
            });
            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }

            runPositioning();
        } catch {
            fail(
                'Could not start face tracking. Please allow camera access and try again.',
            );
        }
    }

    // Step 1: look at the camera until a face is consistently detected.
    function runPositioning() {
        setStatus('positioning');

        let consecutiveDetections = 0;
        let deadline: number | null = null;

        detectionTimerRef.current = setInterval(async () => {
            if (stoppedRef.current) {
                return;
            }

            // Computed lazily on the first tick (inside this callback, not
            // the surrounding handler) so the React Compiler doesn't flag
            // this render-adjacent function as calling an impure API.
            deadline ??= Date.now() + POSITIONING_TIMEOUT_MS;

            if (Date.now() > deadline) {
                fail("We couldn't find your face. Please try again.");

                return;
            }

            const result = await detectFace();

            if (!result) {
                return;
            }

            consecutiveDetections = result ? consecutiveDetections + 1 : 0;

            if (consecutiveDetections >= 3) {
                stopDetectionLoop();

                const frame = captureFrame('selfie-0.jpg');

                if (frame) {
                    selfieFramesRef.current.push(frame);
                }

                runBlinking();
            }
        }, DETECTION_INTERVAL_MS);
    }

    // Step 2: require 3 real blinks (open -> closed -> open cycles) before
    // proceeding - a user who never blinks cannot continue.
    function runBlinking() {
        setStatus('blinking');
        eyeStateRef.current = 'open';
        peakEarRef.current = 0;

        let deadline: number | null = null;
        let completedBlinks = 0;

        detectionTimerRef.current = setInterval(async () => {
            if (stoppedRef.current) {
                return;
            }

            deadline ??= Date.now() + BLINKING_TIMEOUT_MS;

            if (Date.now() > deadline) {
                fail(
                    `We only detected ${completedBlinks} of ${REQUIRED_BLINKS} blinks. Please try again and blink naturally.`,
                );

                return;
            }

            const result = await detectFace();

            if (!result) {
                return;
            }

            peakEarRef.current = Math.max(peakEarRef.current, result.ear);
            const threshold = peakEarRef.current * EAR_DROP_RATIO;
            const isClosed = peakEarRef.current > 0 && result.ear <= threshold;

            if (isClosed && eyeStateRef.current === 'open') {
                eyeStateRef.current = 'closed';
                const frame = captureFrame(
                    `selfie-blink-${completedBlinks}-closed.jpg`,
                );

                if (frame) {
                    selfieFramesRef.current.push(frame);
                }
            } else if (!isClosed && eyeStateRef.current === 'closed') {
                eyeStateRef.current = 'open';
                completedBlinks += 1;
                setBlinkCount(completedBlinks);

                const frame = captureFrame(
                    `selfie-blink-${completedBlinks}-open.jpg`,
                );

                if (frame) {
                    selfieFramesRef.current.push(frame);
                }

                if (completedBlinks >= REQUIRED_BLINKS) {
                    stopDetectionLoop();
                    void runFlashSequence();
                }
            }
        }, DETECTION_INTERVAL_MS);
    }

    // Step 3: flash red/green/blue in sequence and capture one frame per
    // color, so the server can confirm the face's lighting actually reacts.
    async function runFlashSequence() {
        setStatus('flashing');

        const flashFrames: File[] = [];
        const flashColors: string[] = [];

        for (let i = 0; i < FLASH_COLORS.length; i++) {
            if (stoppedRef.current) {
                return;
            }

            setFlashIndex(i);
            await wait(FLASH_DWELL_MS);

            const frame = captureFrame(`flash-${FLASH_COLORS[i]}.jpg`);

            if (frame) {
                flashFrames.push(frame);
                flashColors.push(FLASH_COLORS[i]);
            }
        }

        setFlashIndex(null);
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;

        if (selfieFramesRef.current.length < 3 || flashFrames.length < 3) {
            fail('Capture failed — please try again.');

            return;
        }

        setStatus('done');
        onCaptured({
            selfieFrames: selfieFramesRef.current,
            flashFrames,
            flashColors,
        });
    }

    async function detectFace(): Promise<{ ear: number } | null> {
        if (detectionBusyRef.current) {
            return null;
        }

        const video = videoRef.current;

        if (!video || video.videoWidth === 0) {
            return null;
        }

        detectionBusyRef.current = true;

        try {
            const result = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks(true);

            setFaceDetected(!!result);

            if (!result) {
                clearOverlayBox();

                return null;
            }

            drawOverlayBox(result.detection.box, video);

            const leftEar = eyeAspectRatio(result.landmarks.getLeftEye());
            const rightEar = eyeAspectRatio(result.landmarks.getRightEye());

            return { ear: (leftEar + rightEar) / 2 };
        } catch {
            return null;
        } finally {
            detectionBusyRef.current = false;
        }
    }

    function clearOverlayBox() {
        const canvas = overlayCanvasRef.current;
        const ctx = canvas?.getContext('2d');

        if (canvas && ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }

    function drawOverlayBox(
        box: { x: number; y: number; width: number; height: number },
        video: HTMLVideoElement,
    ) {
        const canvas = overlayCanvasRef.current;

        if (!canvas) {
            return;
        }

        // Sharing the video's intrinsic size + the same CSS object-cover
        // sizing means the browser crops/scales both identically, so the
        // box (already in the video's native pixel space) lines up without
        // extra scaling math.
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#22c55e';
        ctx.lineWidth = Math.max(2, video.videoWidth * 0.005);
        ctx.strokeRect(box.x, box.y, box.width, box.height);
    }

    function captureFrame(filename: string): File | null {
        const video = videoRef.current;
        const canvas = captureCanvasRef.current;

        if (!video || !canvas) {
            return null;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return null;
        }

        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        const bytes = atob(dataUrl.split(',')[1]);
        const array = new Uint8Array(bytes.length);

        for (let i = 0; i < bytes.length; i++) {
            array[i] = bytes.charCodeAt(i);
        }

        return new File([array], filename, { type: 'image/jpeg' });
    }

    function retry() {
        stoppedRef.current = true;
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        stopDetectionLoop();
        setStatus('idle');
        setError(null);
        setFaceDetected(false);
        setBlinkCount(0);
        setFlashIndex(null);
        onReset();
    }

    const isLive =
        status === 'requesting' ||
        status === 'positioning' ||
        status === 'blinking' ||
        status === 'flashing';

    return (
        <div className="flex flex-col gap-3">
            <div className="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-lg border bg-muted">
                {status === 'idle' && (
                    <p className="px-4 text-center text-sm text-muted-foreground">
                        Camera preview will appear here.
                    </p>
                )}
                <video
                    ref={videoRef}
                    muted
                    playsInline
                    className={isLive ? 'h-full w-full object-cover' : 'hidden'}
                />
                <canvas
                    ref={overlayCanvasRef}
                    className={
                        isLive
                            ? 'absolute inset-0 h-full w-full object-cover'
                            : 'hidden'
                    }
                />
                <canvas ref={captureCanvasRef} className="hidden" />

                {status === 'flashing' && flashIndex !== null && (
                    <div
                        className="absolute inset-0 flex items-center justify-center text-sm font-medium text-white/90"
                        style={{
                            backgroundColor:
                                FLASH_BACKGROUND[FLASH_COLORS[flashIndex]],
                        }}
                    >
                        Hold still…
                    </div>
                )}

                {isLive && status !== 'flashing' && (
                    <div className="absolute bottom-2 left-2 flex flex-wrap gap-1.5">
                        <span className="rounded-full bg-black/60 px-3 py-1 text-xs text-white">
                            {status === 'positioning' && 'Look at the camera…'}
                            {status === 'blinking' &&
                                `Blink naturally… ${blinkCount}/${REQUIRED_BLINKS}`}
                            {status === 'requesting' && 'Starting camera…'}
                        </span>
                        <span className="rounded-full bg-black/60 px-3 py-1 text-xs text-white">
                            {faceDetected
                                ? 'Face detected'
                                : 'No face detected'}
                        </span>
                    </div>
                )}

                {status === 'done' && (
                    <p className="px-4 text-center text-sm text-muted-foreground">
                        Capture complete.
                    </p>
                )}
            </div>

            {error && <p className="text-xs text-destructive">{error}</p>}

            {status === 'idle' || status === 'error' ? (
                <Button type="button" variant="outline" onClick={start}>
                    {status === 'error'
                        ? 'Retry capture'
                        : 'Start selfie capture'}
                </Button>
            ) : status === 'done' ? (
                <Button type="button" variant="outline" onClick={retry}>
                    Retake
                </Button>
            ) : (
                <Button type="button" variant="outline" disabled>
                    {status === 'loading' && 'Loading face tracking…'}
                    {status === 'requesting' && 'Requesting camera…'}
                    {status === 'positioning' && 'Positioning…'}
                    {status === 'blinking' && 'Waiting for blinks…'}
                    {status === 'flashing' && 'Flashing…'}
                </Button>
            )}
        </div>
    );
}
