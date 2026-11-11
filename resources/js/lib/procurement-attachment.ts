export function canPreviewAttachment(mimeType: string | null): boolean {
    return ['application/pdf', 'image/png', 'image/jpeg'].includes(mimeType ?? '');
}
