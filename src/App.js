import React, { useEffect, useState } from 'react';
import axios from 'axios';

function App() {
  const [csvData, setCsvData] = useState([]);

  useEffect(() => {
    axios.get('/wp-json/reactdb/v1/csv/read')
      .then(res => setCsvData(res.data))
      .catch(err => console.error(err));
  }, []);

  return (
    <div>
      <h1>CSV Data</h1>
      <pre>{JSON.stringify(csvData, null, 2)}</pre>
    </div>
  );
}

export default App;
