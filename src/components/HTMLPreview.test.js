import React from 'react';
import { render, screen } from '@testing-library/react';
import HTMLPreview, { createPreviewDocument } from './HTMLPreview';

it('escapes row data and substitutes once without interpreting dollar sequences', () => {
  const doc = createPreviewDocument('<p>{{title}}</p><div>{{next}}</div>', '.x{content:"<C>"}', '', {
    title: '<img src=x onerror=alert(1)> $& {{next}}', next: '最後'
  });
  expect(doc).toContain('&lt;img src=x onerror=alert(1)&gt; $&amp; {{next}}</p>');
  expect(doc).toContain('<div>最後</div>');
  expect(doc).toContain('.x{content:"<C>"}');
});
it('isolates template HTML and CSS from the editor in a sandboxed iframe', () => {
  render(<HTMLPreview html="<script>alert(1)</script><p>preview-only</p>" css="body { display:none; }" />);
  const iframe = screen.getByTitle('出力プレビュー');
  expect(iframe).toHaveAttribute('sandbox', '');
  expect(iframe.srcdoc).toContain('body { display:none; }');
  expect(screen.queryByText('preview-only')).not.toBeInTheDocument();
});
