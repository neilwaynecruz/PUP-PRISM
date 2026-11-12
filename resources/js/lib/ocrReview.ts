export type ProcurementDocumentType =
    | 'quotation'
    | 'delivery_receipt'
    | 'invoice';

export type ProcurementOcrDraft = {
    documentType: ProcurementDocumentType;
    reference: string;
    documentDate: string;
    supplier: string;
    productCode: string;
};

/** Only labelled fields are suggested; prices and quantities are never inferred. */
export function suggestProcurementFields(
    text: string,
): Omit<ProcurementOcrDraft, 'documentType'> {
    const field = (pattern: RegExp): string =>
        text.match(pattern)?.[1]?.trim() ?? '';

    return {
        reference: field(
            /^(?:reference|invoice\s*(?:no\.?|number)|quotation\s*(?:no\.?|number)|delivery\s*(?:no\.?|number))\s*[:#]\s*(.+)$/im,
        ).slice(0, 64),
        documentDate: field(/^date\s*:\s*(\d{4}-\d{2}-\d{2})\s*$/im),
        supplier: field(/^supplier\s*:\s*(.+)$/im).slice(0, 120),
        productCode: field(/^(?:sku|product code)\s*:\s*(.+)$/im).slice(0, 64),
    };
}

export function isValidDocumentDate(value: string): boolean {
    if (!value) {
        return true;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);

    return (
        !Number.isNaN(date.valueOf()) &&
        date.toISOString().slice(0, 10) === value
    );
}
