import { Card, CardContent, CardHeader, CardTitle } from "./ui/card";

interface StatCardProps {
    title: string;
    value: number
}

export default function StatCard({ title, value }: StatCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <span className="text-3xl font-bold">{value}</span>
            </CardContent>
        </Card>
    );
}