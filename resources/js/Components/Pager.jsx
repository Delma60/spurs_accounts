import { useState, useMemo } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * Small client-side pager for the account lists. Returns the current slice plus
 * the control to render under it — the data is already loaded, so paging is
 * instant and keeps long histories readable.
 */
export function usePager(items = [], pageSize = 10) {
    const [page, setPage] = useState(1);
    const pages = Math.max(1, Math.ceil(items.length / pageSize));
    const current = Math.min(page, pages);
    const slice = useMemo(
        () => items.slice((current - 1) * pageSize, current * pageSize),
        [items, current, pageSize],
    );

    const control = items.length > pageSize ? (
        <Pager page={current} pages={pages} total={items.length} pageSize={pageSize} onChange={setPage} />
    ) : null;

    return [slice, control];
}

export default function Pager({ page, pages, total, pageSize, onChange }) {
    const from = (page - 1) * pageSize + 1;
    const to = Math.min(total, page * pageSize);

    return (
        <div className="pager">
            <span className="pager__count">{from}–{to} of {total}</span>
            <div className="pager__nav">
                <button type="button" className="pager__btn" disabled={page <= 1}
                    onClick={() => onChange(page - 1)} aria-label="Previous">
                    <ChevronLeft size={16} />
                </button>
                <span className="pager__page">Page {page} of {pages}</span>
                <button type="button" className="pager__btn" disabled={page >= pages}
                    onClick={() => onChange(page + 1)} aria-label="Next">
                    <ChevronRight size={16} />
                </button>
            </div>
        </div>
    );
}
