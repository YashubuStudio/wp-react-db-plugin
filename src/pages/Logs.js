import React from 'react';

const Logs = () => (
  <div>
    <h2 className="text-lg font-bold mb-4">操作ログ</h2>
    <table className="min-w-full border">
      <thead>
        <tr className="bg-gray-200">
          <th className="px-2 py-1 border">日時</th>
          <th className="px-2 py-1 border">ユーザー</th>
          <th className="px-2 py-1 border">操作内容</th>
          <th className="px-2 py-1 border">詳細</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td className="border px-2 py-1">-</td>
          <td className="border px-2 py-1">-</td>
          <td className="border px-2 py-1">-</td>
          <td className="border px-2 py-1">-</td>
        </tr>
      </tbody>
    </table>
  </div>
);

export default Logs;
