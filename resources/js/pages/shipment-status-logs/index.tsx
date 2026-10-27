import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Plus, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';

interface ShipmentStatusLog {
    id: number;
    sequence: number;
    status: string;
    occurred_at: string;
    parcel: { tracking_number: string };
    transit_hub: { name: string } | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function ShipmentStatusLogsIndex({ logs, search: initialSearch }: { logs: Paginator<ShipmentStatusLog>; search: string }) {
    const [search, setSearch] = useState(initialSearch);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('shipment-status-logs.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Status history', href: '/shipment-status-logs' }]}>
            <Head title="Shipment status history" />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Shipment status history</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Status events are kept in chronological sequence per parcel.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={route('parcels.index')}>Parcels</Link></Button>
                        <Button asChild><Link href={route('shipment-status-logs.create')}><Plus /> Add status</Link></Button>
                    </div>
                </div>
                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input
                        aria-label="Search shipment statuses"
                        placeholder="Search tracking, status, or hub"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="secondary"><Search /> Search</Button>
                    {initialSearch && <Button type="button" variant="outline" onClick={() => {
                        setSearch('');
                        router.get(route('shipment-status-logs.index'), {}, { preserveState: true, replace: true });
                    }}>Clear</Button>}
                </form>
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <Table>
                        <TableCaption>Most recent shipment events.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tracking number</TableHead>
                                <TableHead>Sequence</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Transit hub</TableHead>
                                <TableHead>Date and time</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.length ? logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="font-medium">{log.parcel.tracking_number}</TableCell>
                                    <TableCell>#{log.sequence}</TableCell>
                                    <TableCell className="capitalize">{log.status.replaceAll('_', ' ')}</TableCell>
                                    <TableCell>{log.transit_hub?.name ?? '—'}</TableCell>
                                    <TableCell>{new Date(log.occurred_at).toLocaleString()}</TableCell>
                                </TableRow>
                            )) : (
                                <TableRow><TableCell colSpan={5} className="h-32 text-center text-muted-foreground">No status updates have been recorded.</TableCell></TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    {logs.prev_page_url ? <Button variant="outline" size="sm" asChild><Link href={logs.prev_page_url}>Previous</Link></Button> : <span />}
                    <span>Page {logs.current_page} of {logs.last_page}</span>
                    {logs.next_page_url ? <Button variant="outline" size="sm" asChild><Link href={logs.next_page_url}>Next</Link></Button> : <span />}
                </div>
            </main>
        </AppLayout>
    );
}
