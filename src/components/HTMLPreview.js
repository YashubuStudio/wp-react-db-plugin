import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

const HTMLPreview = ({ html, css, data }) => {
  const renderHTML = (template, values, cssText) => {
    const hasTemplate = !!template;
    const hasCss = !!cssText;
    if (!hasTemplate && !hasCss) return '';
    let rendered = template || '';
    Object.keys(values || {}).forEach(key => {
      const regex = new RegExp(`{{${key}}}`, 'g');
      rendered = rendered.replace(regex, values[key]);
    });
    const cssBlock = hasCss ? `<style>${cssText}</style>` : '';
    return cssBlock + rendered;
  };

  return (
    <Box sx={{ border: '1px solid #ddd', p: 2, borderRadius: 2, ml: 4, flex: 1 }}>
      <Typography variant="h6" gutterBottom>プレビュー</Typography>
      <Box dangerouslySetInnerHTML={{ __html: renderHTML(html, data, css) }} />
    </Box>
  );
};

export default HTMLPreview;
