import type { CSSProperties, ReactNode } from 'react';
import type { JSONContent } from '@tiptap/core';

const fontFamilies: Record<string, string> = {
    Inter: 'Inter, sans-serif',
    Poppins: 'Poppins, sans-serif',
    Montserrat: 'Montserrat, sans-serif',
};
const fontSizes = ['12px', '14px', '16px', '18px', '24px', '32px'];
const lineHeights = ['1.25', '1.5', '1.75', '2'];

function textStyle(attrs: Record<string, unknown> | undefined): CSSProperties {
    const style: CSSProperties = {};
    const fontFamily = attrs?.fontFamily;
    const fontSize = attrs?.fontSize;
    const lineHeight = attrs?.lineHeight;

    if (typeof fontFamily === 'string' && fontFamily in fontFamilies) style.fontFamily = fontFamilies[fontFamily];
    if (typeof fontSize === 'string' && fontSizes.includes(fontSize)) style.fontSize = fontSize;
    if (typeof lineHeight === 'string' && lineHeights.includes(lineHeight)) style.lineHeight = Number(lineHeight);

    return style;
}

function renderInline(node: JSONContent, key: string, imageUrl: (mediaId: number) => string): ReactNode {
    if (node.type === 'hardBreak') return <br key={key} />;
    if (node.type === 'articleImage' && Number.isInteger(node.attrs?.mediaId)) return <img alt={typeof node.attrs?.alt === 'string' ? node.attrs.alt : ''} className="mx-1 inline-block max-h-80 max-w-full rounded-lg align-middle" key={key} src={imageUrl(Number(node.attrs?.mediaId))} />;
    if (node.type !== 'text' || typeof node.text !== 'string') return null;

    return (node.marks ?? []).reduce<ReactNode>((content, mark, index) => {
        if (mark.type === 'textStyle') return <span key={`${key}-${index}`} style={textStyle(mark.attrs)}>{content}</span>;
        if (mark.type === 'bold') return <strong key={`${key}-${index}`}>{content}</strong>;
        if (mark.type === 'italic') return <em key={`${key}-${index}`}>{content}</em>;
        if (mark.type === 'strike') return <s key={`${key}-${index}`}>{content}</s>;
        if (mark.type === 'underline') return <u key={`${key}-${index}`}>{content}</u>;
        if (mark.type === 'code') return <code key={`${key}-${index}`}>{content}</code>;
        if (mark.type === 'link' && typeof mark.attrs?.href === 'string') {
            const newTab = mark.attrs.target === '_blank';

            return <a className="text-brand underline underline-offset-2" href={mark.attrs.href} key={`${key}-${index}`} rel={newTab ? 'noopener noreferrer nofollow' : undefined} target={newTab ? '_blank' : undefined} title={typeof mark.attrs.title === 'string' ? mark.attrs.title : undefined}>{content}</a>;
        }

        return content;
    }, node.text);
}

function renderBlock(node: JSONContent, key: string, imageUrl: (mediaId: number) => string): ReactNode {
    const children = (node.content ?? []).map((child, index) => node.type === 'paragraph' || node.type === 'heading'
        ? renderInline(child, `${key}-${index}`, imageUrl)
        : renderBlock(child, `${key}-${index}`, imageUrl));

    if (node.type === 'paragraph') return <p className="my-4 whitespace-pre-wrap leading-7" key={key}>{children}</p>;
    if (node.type === 'heading') {
        const level = [1, 2, 3].includes(Number(node.attrs?.level)) ? Number(node.attrs?.level) : 1;
        const Tag = `h${level}` as 'h1' | 'h2' | 'h3';

        return <Tag className="mt-8 mb-3 font-display font-semibold tracking-tight text-slate-950 dark:text-slate-100 first:mt-0" key={key}>{children}</Tag>;
    }
    if (node.type === 'blockquote') return <blockquote className="my-5 border-l-4 border-brand/40 pl-4 italic text-slate-600 dark:text-slate-300" key={key}>{children}</blockquote>;
    if (node.type === 'bulletList') return <ul className="my-4 list-disc space-y-1 pl-6" key={key}>{children}</ul>;
    if (node.type === 'orderedList') return <ol className="my-4 list-decimal space-y-1 pl-6" key={key}>{children}</ol>;
    if (node.type === 'listItem') return <li className="pl-1" key={key}>{children}</li>;

    return null;
}

export default function ArticlePreviewContent({ content, articleId, publicView = false }: { content: JSONContent; articleId: number; publicView?: boolean }) {
    const imageUrl = (mediaId: number) => publicView ? `/blog/${articleId}/inline-media/${mediaId}` : `/articles/${articleId}/inline-media/${mediaId}`;

    return <div className="break-words text-slate-800 dark:text-slate-200">{(content.content ?? []).map((node, index) => renderBlock(node, String(index), imageUrl))}</div>;
}
