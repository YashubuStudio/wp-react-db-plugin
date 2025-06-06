import React from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

const HTMLPreview = ({ html, data }) => {
  const renderHTML = (template, values) => {
    if (!template) return '';
    let rendered = template;
    Object.keys(values || {}).forEach(key => {
      const regex = new RegExp(`{{${key}}}`, 'g');
      rendered = rendered.replace(regex, values[key]);
    });
    return rendered;
  };

  return (
    <Box sx={{ border: '1px solid #ddd', p: 2, borderRadius: 2, ml: 4, flex: 1 }}>
      <Typography variant="h6" gutterBottom>プレビュー</Typography>
      <Box dangerouslySetInnerHTML={{ __html: renderHTML(html, data) }} />
    </Box>
  );
};

export default HTMLPreview;
