import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PackagePlus, Plus, Search, Truck } from 'lucide-react';
import { Input } from '@/components/ui/input';

interface Parcel {
    id: number;
    tracking_number: string;
    parcel_weight_kg: string;
    delivery_type: string;
    shipping_cost: string;
    sender: { name: string };
    recipient: { name: string };
    current_hub: { name: string } | null;
    status_logs: Array<{ status: string }>;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }];

function statusLabel(status?: string): string {
    return status ? status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'No status';
}

export default function ParcelsIndex({ parcels, search: initialSearch }: { parcels: Paginator<Parcel>; search: string }) {
    const [search, setSearch] = useState(initialSearch);
    const pageTotal = parcels.data.reduce((total, parcel) => total + Number(parcel.shipping_cost), 0);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('parcels.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Parcels" />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <Truck className="size-6 text-primary" />
                            <h1 className="text-2xl font-semibold tracking-tight">Parcel shipments</h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">Track packages, delivery progress, and shipping charges.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('shipment-status-logs.index')}>Status history</Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('parcels.create')}><Plus /> Create parcel</Link>
                        </Button>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input
                        aria-label="Search parcels"
                        placeholder="Search tracking, customer, hub, or status"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="secondary"><Search /> Search</Button>
                    {initialSearch && <Button type="button" variant="outline" onClick={() => {
                        setSearch('');
                        router.get(route('parcels.index'), {}, { preserveState: true, replace: true });
                    }}>Clear</Button>}
                </form>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <Table>
                        <TableCaption>Recent parcels and their latest tracking status.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tracking number</TableHead>
                                <TableHead>Sender → Recipient</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Hub</TableHead>
                                <TableHead>Delivery</TableHead>
                                <TableHead className="text-right">Weight</TableHead>
                                <TableHead className="text-right">Shipping</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {parcels.data.length ? parcels.data.map((parcel) => (
                                <TableRow key={parcel.id}>
                                    <TableCell className="font-medium">{parcel.tracking_number}</TableCell>
                                    <TableCell>{parcel.sender.name} <span className="text-muted-foreground">→</span> {parcel.recipient.name}</TableCell>
                                    <TableCell><span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-medium">{statusLabel(parcel.status_logs.at(-1)?.status)}</span></TableCell>
                                    <TableCell>{parcel.current_hub?.name ?? '—'}</TableCell>
                                    <TableCell className="capitalize">{parcel.delivery_type}</TableCell>
                                    <TableCell className="text-right">{Number(parcel.parcel_weight_kg).toFixed(3)} kg</TableCell>
                                    <TableCell className="text-right">${Number(parcel.shipping_cost).toFixed(2)}</TableCell>
                                </TableRow>
                            )) : (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-40 text-center">
                                        <PackagePlus className="mx-auto mb-2 size-8 text-muted-foreground" />
                                        <p className="font-medium">No parcels yet</p>
                                        <p className="mt-1 text-sm text-muted-foreground">Create your first parcel to start tracking deliveries.</p>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={6}>Shipping total on this page</TableCell>
                                <TableCell className="text-right">${pageTotal.toFixed(2)}</TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    {parcels.prev_page_url ? <Button variant="outline" size="sm" asChild><Link href={parcels.prev_page_url}>Previous</Link></Button> : <span />}
                    <span>Page {parcels.current_page} of {parcels.last_page}</span>
                    {parcels.next_page_url ? <Button variant="outline" size="sm" asChild><Link href={parcels.next_page_url}>Next</Link></Button> : <span />}
                </div>
            </main>
        </AppLayout>
    );
}
