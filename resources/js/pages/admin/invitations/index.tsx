import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { SendHorizonal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuth } from '@/hooks/use-auth';
import admin from '@/routes/admin';
import { Permission, Role, roleOptions } from '@/types';
import type { Paginated } from '@/types';

interface InvitationListItem {
    id: number;
    email: string;
    role: Role | null;
    status: 'pending' | 'accepted' | 'expired';
    invited_by: string | null;
    expires_at: string;
    accepted_at: string | null;
}

interface IndexProps {
    invitations: Paginated<InvitationListItem>;
}

const statusVariant: Record<
    InvitationListItem['status'],
    'default' | 'secondary' | 'destructive'
> = {
    pending: 'default',
    accepted: 'secondary',
    expired: 'destructive',
};

const expiryOptions = [
    { value: 1, label: '1 day' },
    { value: 3, label: '3 days' },
    { value: 7, label: '7 days' },
    { value: 14, label: '14 days' },
    { value: 30, label: '30 days' },
];

export default function Index({ invitations }: IndexProps) {
    const { hasPermission } = useAuth();
    const [inviteOpen, setInviteOpen] = useState(false);

    useEcho('admin-dashboard', '.InvitationSent', () => router.reload());
    useEcho('admin-dashboard', '.UserRegistered', () => router.reload());
    useEcho('admin-dashboard', '.EmailChanged', () => router.reload());

    const [confirmDelete, setConfirmDelete] = useState<number | null>(null);
    const inviteForm = useForm({
        email: '',
        role: Role.User as Role,
        expires_in_days: 3,
    });

    function submitInvite(e: FormEvent) {
        e.preventDefault();
        inviteForm.post(admin.invitations.store().url, {
            onSuccess: () => {
                setInviteOpen(false);
                inviteForm.reset();
            },
        });
    }

    function resend(id: number) {
        router.post(admin.invitations.resend(id).url);
    }

    function deleteInvitation(id: number) {
        router.delete(admin.invitations.destroy(id).url, {
            onSuccess: () => setConfirmDelete(null),
        });
    }

    return (
        <>
            <Head title="Invitations" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-end">
                    {hasPermission(Permission.InviteUserAsAdmin) && (
                        <Button onClick={() => setInviteOpen(true)}>
                            Invite user
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Invited By
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Expires
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {invitations.data.map((inv) => (
                                <tr
                                    key={inv.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-4 py-3">{inv.email}</td>
                                    <td className="px-4 py-3 capitalize">
                                        {inv.role ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={statusVariant[inv.status]}
                                            className="capitalize"
                                        >
                                            {inv.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {inv.invited_by ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {inv.expires_at}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            {inv.status !== 'accepted' && (
                                                <SendHorizonal
                                                    className="cursor-pointer text-muted-foreground/60 hover:text-foreground"
                                                    size="1rem"
                                                    onClick={() =>
                                                        resend(inv.id)
                                                    }
                                                />
                                            )}
                                            <Trash2
                                                className="cursor-pointer text-destructive/60 hover:text-destructive"
                                                size="1rem"
                                                onClick={() =>
                                                    setConfirmDelete(inv.id)
                                                }
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {invitations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No invitations found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {invitations.links.map((link, index) =>
                        link.url ? (
                            <Button
                                key={index}
                                asChild
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                            >
                                <a
                                    href={link.url}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ) : (
                            <Button
                                key={index}
                                size="sm"
                                variant="outline"
                                disabled
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </div>
            </div>

            {hasPermission(Permission.InviteUserAsAdmin) && (
                <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                    <DialogContent
                        onPointerDownOutside={(e) => {
                            if (
                                (e.target as Element).closest?.(
                                    '[data-radix-popper-content-wrapper]',
                                )
                            ) {
                                e.preventDefault();
                            }
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Invite a user</DialogTitle>
                            <DialogDescription>
                                They will receive an email to set their password
                                and access their account.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submitInvite} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="invite-email">
                                    Email address
                                </Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    value={inviteForm.data.email}
                                    onChange={(e) =>
                                        inviteForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="user@example.com"
                                    autoFocus
                                    required
                                />
                                <InputError message={inviteForm.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-role">Role</Label>
                                <Select
                                    value={inviteForm.data.role}
                                    onValueChange={(v) =>
                                        inviteForm.setData('role', v as Role)
                                    }
                                >
                                    <SelectTrigger id="invite-role">
                                        <SelectValue placeholder="Select role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roleOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={inviteForm.errors.role} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-expires">
                                    Link expires in
                                </Label>
                                <Select
                                    value={String(
                                        inviteForm.data.expires_in_days,
                                    )}
                                    onValueChange={(v) =>
                                        inviteForm.setData(
                                            'expires_in_days',
                                            Number(v),
                                        )
                                    }
                                >
                                    <SelectTrigger id="invite-expires">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {expiryOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={String(opt.value)}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={inviteForm.errors.expires_in_days}
                                />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={inviteForm.processing}
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}

            <AlertDialog
                open={confirmDelete !== null}
                onOpenChange={() => setConfirmDelete(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete invitation?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                confirmDelete && deleteInvitation(confirmDelete)
                            }
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        { title: 'Invitations', href: admin.invitations.index() },
    ],
};
