import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

const HTMLPreview = ({ html, css, filterCss, data }) => {
  const renderHTML = (template, values, cssText, extraCssText) => {
    const hasTemplate = !!template;
    const hasCss = !!cssText;
    const hasExtraCss = !!extraCssText;
    if (!hasTemplate && !hasCss && !hasExtraCss) return '';
    let rendered = template || '';
    Object.keys(values || {}).forEach(key => {
      const regex = new RegExp(`{{${key}}}`, 'g');
      rendered = rendered.replace(regex, values[key]);
    });
    const cssParts = [];
    if (hasCss) {
      cssParts.push(cssText);
    }
    if (hasExtraCss) {
      cssParts.push(extraCssText);
    }
    const cssBlock = cssParts.length > 0 ? `<style>${cssParts.join('\n')}</style>` : '';
    return cssBlock + rendered;
  };

  return (
    <Box sx={{ border: '1px solid #ddd', p: 2, borderRadius: 2, width: '100%', boxSizing: 'border-box' }}>
      <Typography variant="h6" gutterBottom>プレビュー</Typography>
      <Box dangerouslySetInnerHTML={{ __html: renderHTML(html, data, css, filterCss) }} />
    </Box>
  );
};

export default HTMLPreview;
