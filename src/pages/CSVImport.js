import React, { useState } from 'react';

const CSVImport = () => {
  const [log, setLog] = useState('');

  const handleUpload = () => {
    // placeholder for upload logic
    setLog('アップロードしました');
  };

  return (
    <div>
      <h2 className="text-lg font-bold mb-4">CSVインポート</h2>
      <div className="border-dashed border-2 p-8 text-center mb-4">
        <input type="file" accept=".csv" />
      </div>
      <button onClick={handleUpload} className="bg-blue-500 text-white px-4 py-2 rounded">
        アップロード
      </button>
      {log && <div className="mt-4">{log}</div>}
    </div>
  );
};

export default CSVImport;
