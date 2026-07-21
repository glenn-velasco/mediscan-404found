import { ChevronLeft, ChevronRight, ZoomIn, ZoomOut } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const MIN_ZOOM = 1;
const MAX_ZOOM = 3;
const ZOOM_STEP = 0.5;

export type ImageViewerImage = { src: string; alt: string };

type Props = {
    images: ImageViewerImage[];
    initialIndex?: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ImageViewerModal({
    images,
    initialIndex = 0,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl border-none bg-transparent p-0 shadow-none sm:max-w-4xl">
                {/* Mounted only while open, so its index state always starts
                    fresh from initialIndex rather than carrying over a
                    prev/next position from a previous time this was opened. */}
                {open && (
                    <ImageViewerBody
                        images={images}
                        initialIndex={initialIndex}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ImageViewerBody({
    images,
    initialIndex,
}: {
    images: ImageViewerImage[];
    initialIndex: number;
}) {
    const [index, setIndex] = useState(initialIndex);
    const [zoom, setZoom] = useState(MIN_ZOOM);

    // Reset zoom directly in these handlers (not an effect reacting to
    // `index`) so switching images never carries over a zoomed-in view.
    const goPrev = useCallback(() => {
        setZoom(MIN_ZOOM);
        setIndex((current) => (current - 1 + images.length) % images.length);
    }, [images.length]);

    const goNext = useCallback(() => {
        setZoom(MIN_ZOOM);
        setIndex((current) => (current + 1) % images.length);
    }, [images.length]);

    const zoomIn = useCallback(() => {
        setZoom((current) => Math.min(MAX_ZOOM, current + ZOOM_STEP));
    }, []);

    const zoomOut = useCallback(() => {
        setZoom((current) => Math.max(MIN_ZOOM, current - ZOOM_STEP));
    }, []);

    const toggleZoom = useCallback(() => {
        setZoom((current) => (current > MIN_ZOOM ? MIN_ZOOM : 2));
    }, []);

    useEffect(() => {
        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'ArrowLeft') {
                goPrev();
            } else if (event.key === 'ArrowRight') {
                goNext();
            } else if (event.key === '+' || event.key === '=') {
                zoomIn();
            } else if (event.key === '-') {
                zoomOut();
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [goPrev, goNext, zoomIn, zoomOut]);

    const current = images[index];

    if (!current) {
        return null;
    }

    const hasMultiple = images.length > 1;
    const isZoomed = zoom > MIN_ZOOM;

    return (
        <>
            <DialogTitle className="sr-only">{current.alt}</DialogTitle>
            <div className="relative flex max-h-[80vh] max-w-[90vw] items-center justify-center overflow-auto rounded-lg">
                <img
                    src={current.src}
                    alt={current.alt}
                    onClick={toggleZoom}
                    style={{ height: `${80 * zoom}vh`, width: 'auto' }}
                    className={cn(
                        'rounded-lg',
                        isZoomed ? 'cursor-zoom-out' : 'cursor-zoom-in',
                    )}
                />
            </div>

            <div className="absolute bottom-2 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/50 px-2 py-1 text-white">
                <button
                    type="button"
                    onClick={zoomOut}
                    disabled={zoom <= MIN_ZOOM}
                    aria-label="Zoom out"
                    className="rounded-full p-1.5 transition-colors hover:bg-black/40 disabled:pointer-events-none disabled:opacity-40"
                >
                    <ZoomOut className="size-4" />
                </button>
                <span className="min-w-10 text-center text-xs">
                    {Math.round(zoom * 100)}%
                </span>
                <button
                    type="button"
                    onClick={zoomIn}
                    disabled={zoom >= MAX_ZOOM}
                    aria-label="Zoom in"
                    className="rounded-full p-1.5 transition-colors hover:bg-black/40 disabled:pointer-events-none disabled:opacity-40"
                >
                    <ZoomIn className="size-4" />
                </button>
                {hasMultiple && (
                    <span className="ml-1 border-l border-white/30 pl-2 text-xs">
                        {index + 1} / {images.length}
                    </span>
                )}
            </div>

            {hasMultiple && (
                <>
                    <button
                        type="button"
                        onClick={goPrev}
                        aria-label="Previous image"
                        className="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition-colors hover:bg-black/70"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={goNext}
                        aria-label="Next image"
                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition-colors hover:bg-black/70"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                </>
            )}
        </>
    );
}
