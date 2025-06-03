import React, { useState } from 'react';

const CSVExport = () => {
  const [downloadUrl, setDownloadUrl] = useState('');

  const handleExport = () => {
    // placeholder for export logic
    setDownloadUrl('/path/to/export.csv');
  };

  return (
    <div>
      <h2 className="text-lg font-bold mb-4">CSVエクスポート</h2>
      <select className="border p-2 mb-4">
        <option>テーブルを選択</option>
      </select>
      <button onClick={handleExport} className="bg-blue-500 text-white px-4 py-2 rounded">
        エクスポート
      </button>
      {downloadUrl && (
        <div className="mt-4">
          <a href={downloadUrl} download className="text-blue-600 underline">
            ダウンロード
          </a>
        </div>
      )}
    </div>
  );
};

export default CSVExport;
