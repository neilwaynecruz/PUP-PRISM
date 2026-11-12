/** Small synthetic fixtures, not institutional documents or a general-purpose PDF writer. */
function assemblePdf(objects: Buffer[]): Buffer {
    const parts = [Buffer.from('%PDF-1.7\n', 'ascii')];
    const offsets = [0];
    let length = parts[0].length;
    objects.forEach((object, index) => {
        offsets.push(length);
        const part = Buffer.concat([
            Buffer.from(`${index + 1} 0 obj\n`),
            object,
            Buffer.from('\nendobj\n'),
        ]);
        parts.push(part);
        length += part.length;
    });
    const xref = `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n${offsets
        .slice(1)
        .map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`)
        .join(
            '',
        )}trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${length}\n%%EOF\n`;

    return Buffer.concat([...parts, Buffer.from(xref)]);
}

export function textPdf(pages: string[][]): Buffer {
    const objects = [
        Buffer.from('<< /Type /Catalog /Pages 2 0 R >>'),
        Buffer.from(
            `<< /Type /Pages /Count ${pages.length} /Kids [${pages.map((_, index) => `${4 + index * 2} 0 R`).join(' ')}] >>`,
        ),
        Buffer.from('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'),
    ];
    pages.forEach((lines, index) => {
        const content = lines
            .map(
                (line, row) =>
                    `BT /F1 30 Tf 30 ${330 - row * 60} Td (${line.replace(/[\\()]/g, '\\$&')}) Tj ET`,
            )
            .join('\n');
        objects.push(
            Buffer.from(
                `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 720 400] /Resources << /Font << /F1 3 0 R >> >> /Contents ${5 + index * 2} 0 R >>`,
            ),
        );
        objects.push(
            Buffer.from(
                `<< /Length ${Buffer.byteLength(content)} >>\nstream\n${content}\nendstream`,
            ),
        );
    });

    return assemblePdf(objects);
}

export function scannedPdf(
    jpeg: Buffer,
    width: number,
    height: number,
): Buffer {
    const content = 'q 720 0 0 240 0 0 cm /Scan Do Q';

    return assemblePdf([
        Buffer.from('<< /Type /Catalog /Pages 2 0 R >>'),
        Buffer.from('<< /Type /Pages /Count 1 /Kids [3 0 R] >>'),
        Buffer.from(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 720 240] /Resources << /XObject << /Scan 4 0 R >> >> /Contents 5 0 R >>',
        ),
        Buffer.concat([
            Buffer.from(
                `<< /Type /XObject /Subtype /Image /Width ${width} /Height ${height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpeg.length} >>\nstream\n`,
            ),
            jpeg,
            Buffer.from('\nendstream'),
        ]),
        Buffer.from(
            `<< /Length ${Buffer.byteLength(content)} >>\nstream\n${content}\nendstream`,
        ),
    ]);
}
