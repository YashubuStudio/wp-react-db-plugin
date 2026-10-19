import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
}[char]));

export const createPreviewDocument = (html, css, filterCss, data = {}) => {
  const rendered = (html || '').replace(/{{([^{}]+)}}/g, (placeholder, key) => (
    Object.prototype.hasOwnProperty.call(data, key) ? escapeHtml(data[key]) : placeholder
  ));
  return `<!doctype html><html lang="ja"><head><meta charset="utf-8"><base target="_blank"><style>body { margin: 0; overflow-wrap: anywhere; }\n${css || ''}\n${filterCss || ''}</style></head><body>${rendered}</body></html>`;
};

const HTMLPreview = ({ html, css, filterCss, data }) => (
  <Box sx={{ border: '1px solid #ddd', p: 2, borderRadius: 2, width: '100%', boxSizing: 'border-box' }}>
    <Typography variant="h6" gutterBottom>プレビュー</Typography>
    <Typography variant="caption" display="block" sx={{ mb: 1 }}>JavaScriptの動作は公開ページで確認してください。</Typography>
    <Box
      component="iframe"
      title="出力プレビュー"
      sandbox=""
      srcDoc={createPreviewDocument(html, css, filterCss, data)}
      sx={{ display: 'block', width: '100%', height: 560, border: 0, bgcolor: 'white' }}
    />
  </Box>
);

export default HTMLPreview;
