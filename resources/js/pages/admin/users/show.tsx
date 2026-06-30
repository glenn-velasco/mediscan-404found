import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { roleOptions } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';

interface UserDetail {
    id: number;
    name: string;
    full_name: string | null;
    email: string;
    role: string | null;
    is_active: boolean;
    created_at: string | null;
}

interface Props {
    user: UserDetail;
}

export default function UserShow({ user }: Props) {
    const [role, setRole] = useState(user.role ?? '');

    const updateRole = () => {
        router.patch(admin.users.role(user.id).url, { role });
    };

    const toggleActivation = () => {
        router.patch(admin.users.activation(user.id).url);
    };

    const destroy = () => {
        if (confirm('Delete this user? This cannot be undone.')) {
            router.delete(admin.users.destroy(user.id).url);
        }
    };

    return (
        <>
            <Head title={user.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardContent className="divide-y p-0">
                        <div className="flex items-center justify-between px-6 py-4">
                            <div className="space-y-1">
                                <p className="font-medium">{user.name}</p>
                                <p className="text-sm text-muted-foreground">{user.email}</p>
                                {user.created_at && (
                                    <p className="text-sm text-muted-foreground">Joined {user.created_at}</p>
                                )}
                            </div>
                            <Badge variant={user.is_active ? 'default' : 'secondary'}>
                                {user.is_active ? 'Active' : 'Deactivated'}
                            </Badge>
                        </div>

                        <div className="px-6 py-4">
                            <p className="mb-2 text-sm font-medium">Role</p>
                            <div className="flex items-center gap-2">
                                <Select value={role} onValueChange={setRole}>
                                    <SelectTrigger className="w-56">
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roleOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button onClick={updateRole} disabled={role === '' || role === user.role}>
                                    Save role
                                </Button>
                            </div>
                        </div>

                        <div className="px-6 py-4">
                            <p className="mb-2 text-sm font-medium">Account actions</p>
                            <div className="flex gap-2">
                                <Button variant="outline" onClick={toggleActivation}>
                                    {user.is_active ? 'Deactivate' : 'Activate'}
                                </Button>
                                <Button variant="destructive" onClick={destroy}>
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

UserShow.layout = ({ user }: Props) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        { title: 'Users', href: admin.users.index() },
        { title: user.full_name ?? user.email, href: admin.users.show(user.id) },
    ],
});
