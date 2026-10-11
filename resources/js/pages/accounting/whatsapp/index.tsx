import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FilterBar from '@/components/accounting/filter-bar';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Check = { key: string; env: string; set: boolean; needed: boolean; what: string };
type Status = { enabled: boolean; mode: string; api_version: string; checks: Check[]; ready: boolean; webhook_url: string | null; default_country_code: string };
type Template = { key: string; name: string; language: string; parameters: string[]; example: string | null };
type Message = {
    id: number; direction: string; to: string; type: string; template: string | null; body: string | null; status: string; wamid: string | null;
    error_code: string | null; error: string | null; attempts: number; mode: string; sent_at: string | null; created_at: string | null;
};
type Props = { messages: Message[]; whatsapp: Status; templates: Template[]; filters: Record<string, string | null> };

const tone: Record<string, string> = {
    queued: 'bg-gray-100 text-gray-700', sent: 'bg-sky-100 text-sky-800', delivered: 'bg-emerald-100 text-emerald-800',
    read: 'bg-emerald-200 text-emerald-900', failed: 'bg-red-100 text-red-700', received: 'bg-violet-100 text-violet-800',
};
const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function WhatsAppIndex({ messages, whatsapp, templates, filters }: Props) {
    const { permissions, flash } = useAccounting();
    const canSend = whatsapp.enabled && permissions['whatsapp.send'];
    const [to, setTo] = useState('');
    const [kind, setKind] = useState<'template' | 'text'>('template');
    const [template, setTemplate] = useState(templates[0]?.key ?? '');
    const [values, setValues] = useState<Record<string, string>>({});
    const [text, setText] = useState('');
    const [queue, setQueue] = useState(false);
    const chosen = templates.find((item) => item.key === template);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const parameters = Object.fromEntries((chosen?.parameters ?? []).map((name) => [name, values[name] ?? '']));
        router.post('/accounting/whatsapp/messages', kind === 'text' ? { to, text, queue } : { to, template, parameters, queue }, { preserveScroll: true });
    };
    const post = (path: string, data: Record<string, string> = {}) => router.post(path, data, { preserveScroll: true });

    return (
        <>
            <Head title="WhatsApp" />
            <div className="space-y-6 p-4">
                <Heading title="WhatsApp" description="Messages sent through the WhatsApp Cloud API, and how far each one got" />
                {!whatsapp.enabled && <Alert><AlertTitle>WhatsApp is switched off</AlertTitle><AlertDescription>Set ACCOUNTING_WHATSAPP_ENABLED=true in .env to send messages.</AlertDescription></Alert>}
                {whatsapp.enabled && whatsapp.mode === 'fake' && <Alert><AlertTitle>Sandbox (mode fake)</AlertTitle><AlertDescription>Nothing leaves this server. Messages are accepted locally and you can move them on by hand. Set ACCOUNTING_WHATSAPP_MODE=live and the token and phone number ID to send for real.</AlertDescription></Alert>}
                {flash?.success && <p className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle className="text-base">Set-up · {whatsapp.mode} · Graph API {whatsapp.api_version}</CardTitle></CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {whatsapp.checks.map((check) => (
                                <div key={check.key} className="flex items-start justify-between gap-3 border-t pt-2 first:border-0 first:pt-0">
                                    <div><p className="font-mono text-xs">{check.env}</p><p className="text-xs text-muted-foreground">{check.what}</p></div>
                                    <Badge variant={check.set ? 'outline' : check.needed ? 'destructive' : 'secondary'}>{check.set ? 'set' : check.needed ? 'missing' : 'optional'}</Badge>
                                </div>
                            ))}
                            {whatsapp.webhook_url && <p className="border-t pt-2 text-xs text-muted-foreground">Webhook URL for Meta: <span className="font-mono text-foreground">{whatsapp.webhook_url}</span></p>}
                            <p className={`text-xs font-medium ${whatsapp.ready ? 'text-emerald-700' : 'text-amber-700'}`}>{whatsapp.ready ? 'Ready.' : 'Not ready: fill in the missing settings.'}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">Send a message</CardTitle></CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-3 text-sm">
                                <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">Phone number
                                    <Input value={to} onChange={(event) => setTo(event.target.value)} placeholder={`+${whatsapp.default_country_code} 300 1234567`} required />
                                </label>
                                <div className="flex gap-4 text-xs">
                                    <label className="flex items-center gap-1"><input type="radio" checked={kind === 'template'} onChange={() => setKind('template')} /> Approved template</label>
                                    <label className="flex items-center gap-1"><input type="radio" checked={kind === 'text'} onChange={() => setKind('text')} /> Free text (within 24 hours of their reply)</label>
                                </div>
                                {kind === 'template' ? (
                                    <>
                                        <select className={selectClass} value={template} onChange={(event) => { setTemplate(event.target.value); setValues({}); }} aria-label="Template">
                                            {templates.map((item) => <option key={item.key} value={item.key}>{item.key} ({item.language})</option>)}
                                        </select>
                                        {chosen?.example && <p className="rounded-md bg-muted p-2 text-xs text-muted-foreground">{chosen.example}</p>}
                                        {chosen?.parameters.map((name) => (
                                            <label key={name} className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">{name}
                                                <Input value={values[name] ?? ''} onChange={(event) => setValues({ ...values, [name]: event.target.value })} required />
                                            </label>
                                        ))}
                                    </>
                                ) : (
                                    <textarea className="min-h-20 w-full rounded-md border border-input bg-background p-2 text-sm" value={text} onChange={(event) => setText(event.target.value)} placeholder="Your message" required />
                                )}
                                <label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={queue} onChange={(event) => setQueue(event.target.checked)} /> Send through the queue</label>
                                <Button type="submit" disabled={!canSend}>Send</Button>
                            </form>
                            {whatsapp.mode === 'fake' && canSend && (
                                <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3 text-xs">
                                    <span className="text-muted-foreground">Sandbox:</span>
                                    <Button size="sm" variant="outline" disabled={to === ''} onClick={() => post('/accounting/whatsapp/simulate-reply', { from: to, text: 'Thank you' })}>Customer replies</Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <FilterBar
                    path="/accounting/whatsapp"
                    values={filters}
                    fields={[
                        { name: 'search', label: 'Search', placeholder: 'Number, text, template or error' },
                        { name: 'status', label: 'Status', type: 'select', options: { queued: 'Queued', sent: 'Sent', delivered: 'Delivered', read: 'Read', failed: 'Failed', received: 'Received' } },
                        { name: 'direction', label: 'Direction', type: 'select', options: { out: 'Sent', in: 'Received' } },
                        { name: 'template', label: 'Template' },
                        { name: 'number', label: 'Number' },
                        { name: 'date_from', label: 'From', type: 'date' },
                        { name: 'date_to', label: 'To', type: 'date' },
                    ]}
                />
                <Card><CardContent className="overflow-x-auto pt-6">
                    <table className="w-full text-sm">
                        <thead><tr className="text-left text-muted-foreground"><th className="py-1">When</th><th>To / from</th><th>Message</th><th>Status</th><th /></tr></thead>
                        <tbody>
                            {messages.map((message) => (
                                <tr key={message.id} className="border-t align-top">
                                    <td className="py-2 text-xs">{message.created_at?.replace('T', ' ').slice(0, 16)}</td>
                                    <td className="font-mono text-xs">{message.direction === 'in' ? '← ' : '→ '}{message.to}</td>
                                    <td>{message.template ? <span className="font-medium">{message.template}</span> : null} <span className="text-xs text-muted-foreground">{message.body}</span>{message.error && <p className="mt-1 max-w-md text-xs text-destructive">{message.error}</p>}{message.wamid && <p className="font-mono text-[10px] text-muted-foreground">{message.wamid}</p>}</td>
                                    <td><span className={`rounded-full px-2 py-0.5 text-xs ${tone[message.status] ?? tone.queued}`}>{message.status}</span></td>
                                    <td className="space-x-1 text-right">
                                        {canSend && message.status === 'failed' && <Button size="sm" variant="outline" onClick={() => post(`/accounting/whatsapp/messages/${message.id}/retry`)}>Retry</Button>}
                                        {canSend && whatsapp.mode === 'fake' && message.direction === 'out' && ['sent', 'delivered'].includes(message.status) && (
                                            <Button size="sm" variant="ghost" onClick={() => post(`/accounting/whatsapp/messages/${message.id}/simulate`, { status: message.status === 'sent' ? 'delivered' : 'read' })}>Mark {message.status === 'sent' ? 'delivered' : 'read'}</Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {messages.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-muted-foreground">No messages yet.</td></tr>}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}

WhatsAppIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'WhatsApp', href: '/accounting/whatsapp' }] };
