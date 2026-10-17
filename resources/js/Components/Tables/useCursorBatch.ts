import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type CursorBatch<T> = { data: T[]; has_more: boolean; next_cursor: string | null };

type Options<T> = {
    initialData: T[];
    initialHasMore: boolean;
    initialNextCursor: string | null;
    batchUrl: string;
    enabled: boolean;
    paused: boolean;
    errorMessage: string;
};

export default function useCursorBatch<T>({
    initialData,
    initialHasMore,
    initialNextCursor,
    batchUrl,
    enabled,
    paused,
    errorMessage,
}: Options<T>) {
    const [rows, setRows] = useState(initialData);
    const [nextCursor, setNextCursor] = useState(initialNextCursor);
    const [hasMore, setHasMore] = useState(initialHasMore);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const sentinelRef = useRef<HTMLDivElement>(null);
    const loadingRef = useRef(false);
    const sequenceRef = useRef(0);
    const controllerRef = useRef<AbortController | null>(null);

    useEffect(() => {
        controllerRef.current?.abort();
        controllerRef.current = null;
        sequenceRef.current += 1;
        loadingRef.current = false;
        setRows(initialData);
        setNextCursor(initialNextCursor);
        setHasMore(initialHasMore);
        setLoading(false);
        setLoadError(null);

        return () => {
            sequenceRef.current += 1;
            controllerRef.current?.abort();
        };
    }, [batchUrl, initialData, initialHasMore, initialNextCursor]);

    const loadMore = useCallback(async () => {
        if (!enabled || paused || loadingRef.current || !hasMore || !nextCursor) return;

        loadingRef.current = true;
        setLoading(true);
        setLoadError(null);
        const sequence = sequenceRef.current;
        const controller = new AbortController();
        controllerRef.current = controller;
        const url = new URL(batchUrl, window.location.origin);
        url.searchParams.set('cursor', nextCursor);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Batch load failed');

            const batch = await response.json() as CursorBatch<T>;
            if (sequence !== sequenceRef.current) return;

            setRows((current) => [...current, ...batch.data]);
            setNextCursor(batch.next_cursor);
            setHasMore(batch.has_more);
        } catch (error) {
            if (sequence === sequenceRef.current && !(error instanceof DOMException && error.name === 'AbortError')) {
                setLoadError(errorMessage);
                toast.error(errorMessage);
            }
        } finally {
            if (sequence === sequenceRef.current) {
                loadingRef.current = false;
                setLoading(false);
                controllerRef.current = null;
            }
        }
    }, [batchUrl, enabled, errorMessage, hasMore, nextCursor, paused]);

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!enabled || paused || !hasMore || loadError || !sentinel || !('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting) void loadMore();
        }, { rootMargin: '180px' });
        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [enabled, hasMore, loadError, loadMore, paused]);

    return { rows, sentinelRef, hasMore, loading, loadError, loadMore };
}
