import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { LivenessCapture } from '@/components/liveness-capture';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import professionalApplication from '@/routes/professional-application';
import { idTypeOptions } from '@/types/idtype';

type Step = 'face' | 'id';

export default function Create() {
    const [step, setStep] = useState<Step>('face');
    const { data, setData, post, processing, errors } = useForm<{
        id_type: string;
        id_photo: File | null;
        selfie_frames: File[];
        flash_frames: File[];
        flash_colors: string[];
    }>({
        id_type: idTypeOptions[0]?.value ?? '',
        id_photo: null,
        selfie_frames: [],
        flash_frames: [],
        flash_colors: [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(professionalApplication.store().url, {
            forceFormData: true,
        });
    }

    function clearFaceCapture() {
        setData('selfie_frames', []);
        setData('flash_frames', []);
        setData('flash_colors', []);
    }

    function redoFaceScan() {
        clearFaceCapture();
        setStep('face');
    }

    return (
        <>
            <Head title="Apply as a Verified Professional" />
            <div className="mx-auto flex max-w-xl flex-col gap-6 p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        Professional Application
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Submit your professional ID and a selfie. We&apos;ll
                        automatically check your submission, then a MediScan
                        admin will review it before it&apos;s approved.
                    </p>
                </div>

                {step === 'face' ? (
                    <div className="flex flex-col gap-3">
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                Step 1 of 2 — Face verification
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Look at the camera, blink naturally 3 times,
                                then hold still through a brief color flash —
                                this confirms you're a live person, not a
                                printed photo or a video. The result is matched
                                against your ID photo and, once approved,
                                becomes your profile picture.
                            </p>
                        </div>
                        <LivenessCapture
                            onCaptured={(result) => {
                                setData('selfie_frames', result.selfieFrames);
                                setData('flash_frames', result.flashFrames);
                                setData('flash_colors', result.flashColors);
                                setStep('id');
                            }}
                            onReset={clearFaceCapture}
                        />
                        <InputError
                            message={
                                errors.selfie_frames ??
                                errors['selfie_frames.0'] ??
                                errors.flash_frames ??
                                errors.flash_colors
                            }
                        />
                    </div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-medium">
                                Step 2 of 2 — ID verification
                            </p>
                            <button
                                type="button"
                                onClick={redoFaceScan}
                                className="text-xs text-muted-foreground underline"
                            >
                                Redo face scan
                            </button>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="id_type">ID Type</Label>
                            <Select
                                value={data.id_type}
                                onValueChange={(v) => setData('id_type', v)}
                            >
                                <SelectTrigger id="id_type">
                                    <SelectValue placeholder="Select an ID type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {idTypeOptions.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.id_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="id_photo">
                                Professional ID photo
                            </Label>
                            <Input
                                id="id_photo"
                                type="file"
                                accept="image/png,image/jpeg"
                                onChange={(e) =>
                                    setData(
                                        'id_photo',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={errors.id_photo} />
                        </div>

                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                data.selfie_frames.length < 3 ||
                                data.flash_frames.length < 3
                            }
                        >
                            {processing ? 'Submitting…' : 'Submit application'}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
