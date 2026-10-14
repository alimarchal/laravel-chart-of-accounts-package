import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useAccounting } from '@/lib/accounting';

type Topic = { slug: string; title: string; summary: string; url: string };
type Block = { key: string; title: string; topics: Topic[] };

const prose =
    'max-w-3xl text-[0.95rem] leading-7 [&_a]:font-medium [&_a]:text-primary [&_a:hover]:underline [&_blockquote]:my-3 [&_blockquote]:rounded-r-md [&_blockquote]:border-l-4 [&_blockquote]:border-primary [&_blockquote]:bg-muted/60 [&_blockquote]:px-4 [&_blockquote]:py-2 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm [&_h2]:mt-7 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:font-semibold [&_kbd]:rounded [&_kbd]:border [&_kbd]:px-1.5 [&_kbd]:text-xs [&_li]:my-1 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_table]:my-3 [&_table]:w-full [&_table]:text-sm [&_td]:border-b [&_td]:px-3 [&_td]:py-1.5 [&_th]:border-b [&_th]:px-3 [&_th]:py-1.5 [&_th]:text-left [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6';

export default function HelpShow({ article, html, blocks }: { article: { slug: string; title: string; summary: string }; html: string; blocks: Block[] }) {
    useAccounting();

    return (
        <>
            <Head title={`Help: ${article.title}`} />
            <div className="space-y-6 p-4">
                <Heading title={article.title} description={article.summary} />
                <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside className="space-y-1 text-sm lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-auto print:hidden" aria-label="Help topics">
                        <Link href="/accounting/help" className="mb-2 block font-medium text-primary hover:underline">
                            ← All help topics
                        </Link>
                        {blocks.map((block) => (
                            <details key={block.key} open={block.topics.some((topic) => topic.slug === article.slug)}>
                                <summary className="cursor-pointer py-1.5 font-semibold">{block.title}</summary>
                                <div className="flex flex-col">
                                    {block.topics.map((topic) => (
                                        <Link key={topic.slug} href={topic.url} className={`rounded-md px-3 py-1 hover:bg-accent ${topic.slug === article.slug ? 'bg-accent font-semibold text-primary' : 'text-muted-foreground'}`}>
                                            {topic.title}
                                        </Link>
                                    ))}
                                </div>
                            </details>
                        ))}
                    </aside>
                    <article className="rounded-xl border bg-card p-6 shadow-xs">
                        {/* The markdown comes from the package's own help files (or the application's overrides), rendered on the server. */}
                        <div className={prose} dangerouslySetInnerHTML={{ __html: html }} />
                    </article>
                </div>
            </div>
        </>
    );
}

HelpShow.layout = { breadcrumbs: [{ title: 'Accounting', href: '/accounting' }, { title: 'Help centre', href: '/accounting/help' }] };
