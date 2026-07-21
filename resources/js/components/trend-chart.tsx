import {
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';

interface TrendPoint {
    date: string;
    value: number;
}

interface TrendChartProps {
    title: string;
    subtitle?: string;
    data: TrendPoint[];
}

export default function TrendChart({ title, subtitle, data }: TrendChartProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="px-2 sm:px-6">
                <ResponsiveContainer
                    width="100%"
                    height={180}
                    className="sm:!h-[200px]"
                >
                    <LineChart data={data} margin={{ left: -20, right: 8 }}>
                        <XAxis
                            dataKey="date"
                            tick={{ fontSize: 11 }}
                            tickFormatter={(date: string) => date.slice(5)}
                            minTickGap={16}
                        />
                        <YAxis
                            allowDecimals={false}
                            tick={{ fontSize: 11 }}
                            width={28}
                        />
                        <Tooltip
                            formatter={(value) => [`${value}`, 'Count']}
                            contentStyle={{
                                fontSize: 12,
                                lineHeight: 1.3,
                                padding: '4px 8px',
                                background: 'var(--popover)',
                                color: 'var(--popover-foreground)',
                                border: '1px solid var(--border)',
                                borderRadius: 6,
                            }}
                            labelStyle={{ fontSize: 11, marginBottom: 2 }}
                            itemStyle={{ fontSize: 12, padding: 0 }}
                            wrapperStyle={{ outline: 'none' }}
                        />
                        <Line
                            type="monotone"
                            dataKey="value"
                            stroke="currentColor"
                            className="text-primary"
                            strokeWidth={2}
                            dot={false}
                        />
                    </LineChart>
                </ResponsiveContainer>
                {subtitle && (
                    <p className="text-right text-xs text-muted-foreground">
                        {subtitle}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
