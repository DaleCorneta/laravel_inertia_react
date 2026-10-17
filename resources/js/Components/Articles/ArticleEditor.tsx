import { useState } from 'react';
import type { ReactNode } from 'react';
import axios from 'axios';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import { Node } from '@tiptap/core';
import type { JSONContent } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { FontFamily, FontSize, LineHeight, TextStyle } from '@tiptap/extension-text-style';

const fonts = ['Inter', 'Poppins', 'Montserrat'] as const;
const fontSizes = ['12px', '14px', '16px', '18px', '24px', '32px'] as const;
const lineHeights = ['1.25', '1.5', '1.75', '2'] as const;

type ArticleEditorProps = {
    value: JSONContent;
    error?: string;
    onChange: (content: JSONContent) => void;
    articleId?: number;
};

function ToolButton({ label, children = label, active = false, toggle = false, onClick }: { label: string; children?: ReactNode; active?: boolean; toggle?: boolean; onClick: () => void }) {
    return (
        <button
            aria-label={label}
            aria-pressed={toggle ? active : undefined}
            className={`min-h-9 rounded-lg border px-3 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-brand/15 ${active ? 'border-brand bg-brand/10 text-brand' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`}
            onClick={onClick}
            title={label}
            type="button"
        >
            {children}
        </button>
    );
}

export default function ArticleEditor({ value, error, onChange, articleId }: ArticleEditorProps) {
    const [linkUrl, setLinkUrl] = useState('');
    const [imageAlt, setImageAlt] = useState('');
    const [imageError, setImageError] = useState('');
    const [uploading, setUploading] = useState(false);
    const editor = useEditor({
        extensions: [
            Node.create({
                name: 'articleImage',
                group: 'inline',
                inline: true,
                atom: true,
                draggable: true,
                addAttributes() {
                    return {
                        mediaId: { default: null, parseHTML: (element) => Number(element.getAttribute('data-media-id')), renderHTML: (attributes) => ({ 'data-media-id': attributes.mediaId }) },
                        alt: { default: '', renderHTML: (attributes) => ({ alt: attributes.alt }) },
                    };
                },
                parseHTML: () => [{ tag: 'img[data-media-id]' }],
                renderHTML: ({ node, HTMLAttributes }) => ['img', { ...HTMLAttributes, src: articleId ? `/articles/${articleId}/inline-media/${node.attrs.mediaId}` : '', class: 'inline-block max-h-80 max-w-full rounded-lg align-middle' }],
            }),
            StarterKit.configure({
                codeBlock: false,
                heading: { levels: [1, 2, 3] },
                horizontalRule: false,
                link: { autolink: false, linkOnPaste: false, openOnClick: false, HTMLAttributes: { target: null, rel: null, class: null } },
            }),
            TextStyle,
            FontFamily,
            FontSize,
            LineHeight,
        ],
        content: value,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                'aria-describedby': error ? 'content-help content-error' : 'content-help',
                'aria-invalid': String(Boolean(error)),
                'aria-label': 'Article content',
                class: 'min-h-64 px-4 py-3 text-sm leading-7 text-slate-900 dark:text-slate-100 outline-none',
            },
        },
        onUpdate: ({ editor: currentEditor }) => onChange(currentEditor.getJSON()),
    });
    const editorState = useEditorState({
        editor,
        selector: ({ editor: currentEditor }) => {
            const textStyle = currentEditor?.getAttributes('textStyle');

            return {
                block: currentEditor?.isActive('heading', { level: 1 }) ? '1'
                    : currentEditor?.isActive('heading', { level: 2 }) ? '2'
                        : currentEditor?.isActive('heading', { level: 3 }) ? '3'
                            : currentEditor?.isActive('blockquote') ? 'blockquote' : 'paragraph',
                bold: currentEditor?.isActive('bold') ?? false,
                italic: currentEditor?.isActive('italic') ?? false,
                underline: currentEditor?.isActive('underline') ?? false,
                strike: currentEditor?.isActive('strike') ?? false,
                bulletList: currentEditor?.isActive('bulletList') ?? false,
                orderedList: currentEditor?.isActive('orderedList') ?? false,
                fontFamily: textStyle?.fontFamily ?? '',
                fontSize: textStyle?.fontSize ?? '',
                lineHeight: textStyle?.lineHeight ?? '',
            };
        },
    });

    function applyBlock(value: string) {
        if (!editor) return;
        if (value === 'paragraph') editor.chain().focus().setParagraph().run();
        else if (value === 'blockquote') editor.chain().focus().toggleBlockquote().run();
        else editor.chain().focus().toggleHeading({ level: Number(value) as 1 | 2 | 3 }).run();
    }

    async function uploadImage(file?: File) {
        if (!articleId || !file) return;
        setImageError('');
        setUploading(true);
        const data = new FormData();
        data.append('image', file);
        data.append('alt', imageAlt);
        try {
            const response = await axios.post<{ mediaId: number; alt: string }>(`/articles/${articleId}/inline-media`, data);
            editor?.chain().focus().insertContent({ type: 'articleImage', attrs: response.data }).run();
        } catch {
            setImageError('Image upload failed. Choose a JPEG or PNG image up to 4 MB.');
        } finally {
            setUploading(false);
        }
    }

    return (
        <div className={`overflow-hidden rounded-lg border bg-white dark:bg-slate-900 focus-within:ring-4 focus-within:ring-brand/10 ${error ? 'border-red-500' : 'border-slate-300 dark:border-slate-700 focus-within:border-brand'}`}>
            <div aria-label="Article formatting" className="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 p-3" role="toolbar">
                <label className="sr-only" htmlFor="article-block-style">Block style</label>
                <select className="h-9 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-sm text-slate-700 dark:text-slate-200" id="article-block-style" onChange={(event) => applyBlock(event.target.value)} value={editorState?.block ?? 'paragraph'}>
                    <option value="paragraph">Paragraph</option>
                    <option value="1">Heading 1</option>
                    <option value="2">Heading 2</option>
                    <option value="3">Heading 3</option>
                    <option value="blockquote">Quote</option>
                </select>
                <ToolButton active={editorState?.bold} label="Bold" onClick={() => editor?.chain().focus().toggleBold().run()} toggle>B</ToolButton>
                <ToolButton active={editorState?.italic} label="Italic" onClick={() => editor?.chain().focus().toggleItalic().run()} toggle><span className="italic">I</span></ToolButton>
                <ToolButton active={editorState?.underline} label="Underline" onClick={() => editor?.chain().focus().toggleUnderline().run()} toggle><span className="underline">U</span></ToolButton>
                <ToolButton active={editorState?.strike} label="Strikethrough" onClick={() => editor?.chain().focus().toggleStrike().run()} toggle><span className="line-through">S</span></ToolButton>
                <ToolButton active={editorState?.bulletList} label="Bulleted list" onClick={() => editor?.chain().focus().toggleBulletList().run()} toggle>• List</ToolButton>
                <ToolButton active={editorState?.orderedList} label="Numbered list" onClick={() => editor?.chain().focus().toggleOrderedList().run()} toggle>1. List</ToolButton>
                <label className="sr-only" htmlFor="article-font-family">Font family</label>
                <select className="h-9 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-sm text-slate-700 dark:text-slate-200" id="article-font-family" onChange={(event) => event.target.value ? editor?.chain().focus().setFontFamily(event.target.value).run() : editor?.chain().focus().unsetFontFamily().run()} value={editorState?.fontFamily ?? ''}>
                    <option value="">Default font</option>
                    {fonts.map((font) => <option key={font} value={font}>{font}</option>)}
                </select>
                <label className="sr-only" htmlFor="article-font-size">Font size</label>
                <select className="h-9 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-sm text-slate-700 dark:text-slate-200" id="article-font-size" onChange={(event) => event.target.value ? editor?.chain().focus().setFontSize(event.target.value).run() : editor?.chain().focus().unsetFontSize().run()} value={editorState?.fontSize ?? ''}>
                    <option value="">Default size</option>
                    {fontSizes.map((size) => <option key={size} value={size}>{size}</option>)}
                </select>
                <label className="sr-only" htmlFor="article-line-height">Line spacing</label>
                <select className="h-9 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-sm text-slate-700 dark:text-slate-200" id="article-line-height" onChange={(event) => event.target.value ? editor?.chain().focus().setLineHeight(event.target.value).run() : editor?.chain().focus().unsetLineHeight().run()} value={editorState?.lineHeight ?? ''}>
                    <option value="">Default spacing</option>
                    {lineHeights.map((height) => <option key={height} value={height}>{height} line spacing</option>)}
                </select>
                <label className="sr-only" htmlFor="article-link-url">Link URL</label>
                <input className="h-9 min-w-36 flex-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm text-slate-700 dark:text-slate-200 focus:border-brand focus:ring-brand" id="article-link-url" maxLength={2048} onChange={(event) => setLinkUrl(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') event.preventDefault(); }} placeholder="Link URL" value={linkUrl} />
                <ToolButton label="Apply link" onClick={() => {
                    const href = linkUrl.trim();
                    if (href) editor?.chain().focus().extendMarkRange('link').setLink({ href }).run();
                }}>Link</ToolButton>
                <ToolButton label="Remove link" onClick={() => editor?.chain().focus().unsetLink().run()}>Unlink</ToolButton>
                {articleId && <>
                    <label className="sr-only" htmlFor="article-image-alt">Image description</label>
                    <input className="h-9 min-w-36 flex-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm text-slate-700 dark:text-slate-200" id="article-image-alt" maxLength={255} onChange={(event) => setImageAlt(event.target.value)} placeholder="Image description" value={imageAlt} />
                    <label className={`inline-flex min-h-9 cursor-pointer items-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 ${uploading ? 'pointer-events-none opacity-60' : ''}`}>
                        {uploading ? 'Uploading…' : 'Insert image'}
                        <input accept="image/jpeg,image/png" className="sr-only" disabled={uploading} onChange={(event) => { void uploadImage(event.target.files?.[0]); event.currentTarget.value = ''; }} type="file" />
                    </label>
                </>}
            </div>
            {imageError && <p className="border-b border-red-100 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{imageError}</p>}
            <EditorContent editor={editor} />
        </div>
    );
}
