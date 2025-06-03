import React from 'react';

const DatabaseManager = () => (
  <div className="flex">
    <div className="w-1/4 pr-4 border-r">
      <h2 className="font-bold mb-2">テーブル一覧</h2>
      <ul>
        <li>sample_table</li>
      </ul>
    </div>
    <div className="w-3/4 pl-4">
      <h2 className="font-bold mb-2">テーブル内容</h2>
      <p>ここに選択したテーブルの内容を表示します。</p>
    </div>
  </div>
);

export default DatabaseManager;
