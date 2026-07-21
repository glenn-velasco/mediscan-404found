import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

export function CopyableField({
    label,
    value,
}: {
    label: string;
    value: string | number | null;
}) {
    const [copiedText, copy] = useClipboard();
    const text = value !== null && value !== undefined ? String(value) : null;
    const CopyIcon = copiedText === text ? Check : Copy;

    return (
        <div className="flex flex-col gap-1">
            <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </span>
            <div className="flex items-center gap-1">
                <span className="text-sm font-medium text-foreground">
                    {text ?? '—'}
                </span>
                {text && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-5"
                        onClick={() => copy(text)}
                    >
                        <CopyIcon className="size-3" />
                    </Button>
                )}
            </div>
        </div>
    );
}
