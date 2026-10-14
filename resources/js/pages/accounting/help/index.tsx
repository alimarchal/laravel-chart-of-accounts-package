import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { useAccounting } from '@/lib/accounting';

type Topic = { slug: string; title: string; summary: string; url: string };
type Block = { key: string; title: string; topics: Topic[] };

export default function HelpIndex({ blocks }: { blocks: Block[] }) {
    useAccounting();
    const [query, setQuery] = useState('');
    const match = (topic: Topic) => `${topic.title} ${topic.summary}`.toLowerCase().includes(query.trim().toLowerCase());

    return (
        <>
            <Head title="Help centre" />
            <div className="space-y-6 p-4">
                <Heading title="Help centre" description="Short guides for every screen, in plain language: what it is for, when to use it, and how." />
                <div className="relative max-w-xl">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search the help: journal, payroll, reconciliation …" className="pl-9" aria-label="Search the help" />
                </div>
                {blocks.map((block) => {
                    const topics = block.topics.filter(match);

                    return topics.length === 0 ? null : (
                        <section key={block.key} className="space-y-3">
                            <h2 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">{block.title}</h2>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {topics.map((topic) => (
                                    <Link key={topic.slug} href={topic.url} className="flex flex-col gap-1 rounded-xl border bg-card p-4 shadow-xs transition hover:-translate-y-0.5 hover:border-primary">
                                        <span className="font-semibold">{topic.title}</span>
                                        <span className="text-sm text-muted-foreground">{topic.summary}</span>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </>
    );
}

HelpIndex.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Help centre', href: '/accounting/help' }] };
