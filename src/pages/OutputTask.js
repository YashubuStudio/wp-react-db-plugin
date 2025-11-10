import React, { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormGroup from '@mui/material/FormGroup';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import isPlugin, { apiNonce, apiEndpoint } from '../isPlugin';
import HTMLPreview from '../components/HTMLPreview';

const FILTER_TYPES = [
  { value: 'text', label: '値をそのまま使用' },
  { value: 'date', label: '日付としてフォーマット' },
  { value: 'list', label: '区切り文字で分割' }
];

const SORT_OPTIONS = [
  { value: 'asc', label: '昇順 (A→Z)' },
  { value: 'desc', label: '降順 (Z→A)' },
  { value: 'none', label: '登録順' }
];

const FILTER_CSS_TEMPLATE = `/* === Filter CSS Template (matches default front-end styles) === */
.reactdb-tabbed-output {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 1.5rem;
  width: 100%;
}

.reactdb-tabbed-output .reactdb-output-controlPanel {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
}

.reactdb-tabbed-output .reactdb-output-searchBlock {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid #e3e3e3;
  border-radius: 0.75rem;
  background: #f9fafc;
}

.reactdb-tabbed-output .reactdb-output-searchLabel {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.reactdb-tabbed-output .reactdb-output-searchTitle {
  font-weight: 600;
  font-size: 0.95rem;
}

.reactdb-tabbed-output .reactdb-output-searchInput {
  padding: 0.5rem 0.85rem;
  border: 1px solid #d0d7de;
  border-radius: 999px;
  font-size: 0.95rem;
  max-width: 100%;
}

.reactdb-tabbed-output .reactdb-output-searchInput:focus {
  outline: none;
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.15);
}

.reactdb-tabbed-output .reactdb-output-filterGroup {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  border: 1px solid #e3e3e3;
  border-radius: 0.75rem;
  background: #ffffff;
}

.reactdb-tabbed-output .reactdb-output-filterTitle {
  font-weight: 600;
  font-size: 0.95rem;
}

.reactdb-tabbed-output .reactdb-output-filterList {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.reactdb-tabbed-output .reactdb-output-filterButton {
  border: 1px solid #d0d7de;
  border-radius: 999px;
  padding: 0.35rem 0.85rem;
  background: #f1f5f9;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.reactdb-tabbed-output .reactdb-output-filterButton:hover {
  background: #e2e8f0;
}

.reactdb-tabbed-output .reactdb-output-filterButton.is-active {
  background: #1976d2;
  color: #fff;
  border-color: #1976d2;
  box-shadow: 0 0 0 1px rgba(25, 118, 210, 0.3);
}

.reactdb-tabbed-output .reactdb-tabbed-content {
  width: 100%;
}

.reactdb-tabbed-output .reactdb-output-items {
  display: grid;
  gap: 1rem;
  width: 100%;
}

.reactdb-tabbed-output .reactdb-default-row {
  padding: 0.75rem;
  border: 1px solid #e0e0e0;
  border-radius: 0.5rem;
  background: #fff;
}

@media (min-width: 768px) {
  .reactdb-tabbed-output .reactdb-output-items {
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }
}
`;

const FILTER_DEFAULTS = {
  label: '',
  column: '',
  type: 'text',
  dateFormat: 'Y-m-d',
  delimiter: ',',
  sort: 'asc',
  labelTemplate: ''
};

const createFilterId = () => `filter_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;

const createFilter = (overrides = {}) => ({
  ...FILTER_DEFAULTS,
  id: createFilterId(),
  ...overrides
});

const sanitizeFilter = (filter, fallbackIndex = 0) => {
  const base = { ...FILTER_DEFAULTS, ...(filter || {}) };
  const id = typeof base.id === 'string' && base.id ? base.id : `${createFilterId()}_${fallbackIndex}`;
  const type = FILTER_TYPES.some(opt => opt.value === base.type) ? base.type : 'text';
  const sort = SORT_OPTIONS.some(opt => opt.value === base.sort) ? base.sort : 'asc';
  return {
    ...base,
    id,
    type,
    sort,
    column: typeof base.column === 'string' ? base.column : '',
    label: typeof base.label === 'string' ? base.label : '',
    dateFormat: typeof base.dateFormat === 'string' && base.dateFormat ? base.dateFormat : 'Y-m-d',
    delimiter: typeof base.delimiter === 'string' && base.delimiter !== '' ? base.delimiter : ',',
    labelTemplate: typeof base.labelTemplate === 'string' ? base.labelTemplate : ''
  };
};

const normalizeFilters = (rawFilters, fallbackDateField, fallbackCategoryField) => {
  if (Array.isArray(rawFilters) && rawFilters.length > 0) {
    return rawFilters.map((f, idx) => sanitizeFilter(f, idx));
  }
  const fallback = [];
  if (fallbackDateField) {
    fallback.push(sanitizeFilter({ id: 'date', label: '日付', column: fallbackDateField, type: 'date', dateFormat: 'Y-m-d' }));
  }
  if (fallbackCategoryField) {
    fallback.push(sanitizeFilter({ id: 'category', label: 'カテゴリ', column: fallbackCategoryField, type: 'text' }));
  }
  return fallback;
};

const ensureValidFilters = (filters, availableColumns) => {
  if (!Array.isArray(filters)) {
    return [];
  }
  if (!Array.isArray(availableColumns) || availableColumns.length === 0) {
    return filters;
  }
  return filters.map(filter => {
    if (!filter.column) {
      return filter;
    }
    if (!availableColumns.includes(filter.column)) {
      return { ...filter, column: '', label: filter.label === filter.column ? '' : filter.label };
    }
    return filter;
  });
};

const serializeFilters = filters => (Array.isArray(filters) ? filters : []).map(filter => ({
  id: filter.id,
  label: filter.label,
  column: filter.column,
  type: filter.type,
  dateFormat: filter.dateFormat,
  delimiter: filter.delimiter,
  sort: filter.sort,
  labelTemplate: filter.labelTemplate
}));

const OutputTask = () => {
  const { task } = useParams();
  const [settings, setSettings] = useState({});
  const [config, setConfig] = useState({ table: '', format: 'html', html: '', css: '', filterCss: FILTER_CSS_TEMPLATE, filters: [], search: { enabled: false, columns: [] } });
  const [tables, setTables] = useState([]);
  const [columns, setColumns] = useState([]);
  const [sampleRow, setSampleRow] = useState(null);
  const applySettingsToConfig = useCallback((map) => {
    const entry = map && map[task] ? map[task] : {};
    const existingFilterCss = typeof entry.filterCss === 'string' ? entry.filterCss : '';
    setConfig({
      table: entry.table || '',
      format: entry.format || 'html',
      html: entry.html || '',
      css: entry.css || '',
      filterCss: existingFilterCss && existingFilterCss.trim() ? existingFilterCss : FILTER_CSS_TEMPLATE,
      filters: normalizeFilters(entry.filters, entry.dateField, entry.categoryField),
      search: {
        enabled: !!(entry.search && (entry.search.enabled || entry.search === true)),
        columns: Array.isArray(entry.search && entry.search.columns)
          ? entry.search.columns.filter(col => typeof col === 'string')
          : []
      }
    });
  }, [task]);
  // fetch columns and sample row when table selected
  useEffect(() => {
    const selectedTable = config.table;
    const currentFormat = config.format;
    if (!selectedTable) {
      setColumns([]);
      setSampleRow(null);
      return;
    }
    const handleCols = (cols) => {
      const names = Array.isArray(cols) ? cols : [];
      setColumns(names);
      setConfig(cfg => {
        const next = { ...cfg };
        if (currentFormat === 'html' && names.length > 0 && !cfg.html) {
          const snippet = `<div class="reactdb-row">\n  ${names
            .map(c => `{{${c}}}`)
            .join(' | ')}\n</div>`;
          next.html = snippet;
        }
        next.filters = ensureValidFilters(cfg.filters, names);
        if (cfg.search && Array.isArray(cfg.search.columns)) {
          next.search = {
            enabled: !!cfg.search.enabled && cfg.search.columns.some(column => names.includes(column)),
            columns: cfg.search.columns.filter(column => names.includes(column))
          };
        }
        return next;
      });
    };
    if (isPlugin) {
      fetch(apiEndpoint(`table/info?name=${selectedTable}`), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => handleCols(Array.isArray(data) ? data.map(c => c.Field) : []))
        .catch(() => handleCols([]));
      fetch(apiEndpoint(`table/export?name=${selectedTable}`), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(rows => setSampleRow(Array.isArray(rows) && rows.length > 0 ? rows[0] : null))
        .catch(() => setSampleRow(null));
    } else {
      handleCols(['id', 'value']);
      setSampleRow({ id: 1, value: 'sample' });
    }
    }, [config.table, config.format]);

  useEffect(() => {
    if (isPlugin) {
      fetch(apiEndpoint('output/settings'), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => {
          setSettings(data);
          applySettingsToConfig(data);
        });
      fetch(apiEndpoint('tables'), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': apiNonce }
      })
        .then(r => r.json())
        .then(data => setTables(Array.isArray(data) ? data : []));
    } else {
      setTables(['demo_table']);
      applySettingsToConfig({});
    }
  }, [task, applySettingsToConfig]);

  useEffect(() => {
    setConfig(cfg => {
      if (!cfg.search || !Array.isArray(cfg.search.columns)) {
        return cfg;
      }
      const valid = cfg.search.columns.filter(column => columns.includes(column));
      if (valid.length === cfg.search.columns.length) {
        return cfg;
      }
      return {
        ...cfg,
        search: {
          ...cfg.search,
          enabled: cfg.search.enabled && valid.length > 0,
          columns: valid
        }
      };
    });
  }, [columns]);

  const handleSave = () => {
    if (!task || !config.table) return;
    const searchColumns = Array.isArray(config.search?.columns)
      ? config.search.columns.filter(column => columns.includes(column))
      : [];
    const preparedConfig = {
      ...config,
      filters: serializeFilters(config.filters),
      dateField: '',
      categoryField: '',
      search: {
        enabled: Boolean(config.search?.enabled) && searchColumns.length > 0,
        columns: searchColumns
      }
    };
    const newSettings = { ...settings, [task]: preparedConfig };
    if (isPlugin) {
      fetch(apiEndpoint('output/settings'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
        body: JSON.stringify({ settings: newSettings })
      })
        .then(r => r.json())
        .then(data => {
          setSettings(data);
          applySettingsToConfig(data);
        });
    } else {
      setSettings(newSettings);
      applySettingsToConfig(newSettings);
    }
  };

  const handleDelete = () => {
    if (!task) return;
    const newSettings = { ...settings };
    delete newSettings[task];
    if (isPlugin) {
      fetch(apiEndpoint('output/settings'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': apiNonce },
        body: JSON.stringify({ settings: newSettings })
      })
        .then(r => r.json())
        .then(data => {
          setSettings(data);
          applySettingsToConfig(data);
        });
    } else {
      setSettings(newSettings);
      applySettingsToConfig(newSettings);
    }
  };

  const mutateFilter = (id, updater) => {
    setConfig(cfg => {
      const current = Array.isArray(cfg.filters) ? cfg.filters : [];
      return {
        ...cfg,
        filters: current.map(filter => (filter.id === id ? updater(filter) : filter))
      };
    });
  };

  const updateFilter = (id, patch) => {
    mutateFilter(id, filter => ({ ...filter, ...patch }));
  };

  const removeFilter = (id) => {
    setConfig(cfg => {
      const current = Array.isArray(cfg.filters) ? cfg.filters : [];
      return {
        ...cfg,
        filters: current.filter(filter => filter.id !== id)
      };
    });
  };

  const addFilter = () => {
    setConfig(cfg => {
      const current = Array.isArray(cfg.filters) ? cfg.filters : [];
      return {
        ...cfg,
        filters: [...current, createFilter({ label: `フィルター${current.length + 1}` })]
      };
    });
  };

  const setFilterColumn = (id, value) => {
    mutateFilter(id, filter => {
      const next = { ...filter, column: value };
      if (!filter.label) {
        next.label = value;
      }
      return next;
    });
  };

  const setFilterType = (id, value) => {
    mutateFilter(id, filter => {
      const next = { ...filter, type: value };
      if (value === 'date' && !filter.dateFormat) {
        next.dateFormat = 'Y-m-d';
      }
      if (value === 'list' && !filter.delimiter) {
        next.delimiter = ',';
      }
      return next;
    });
  };

  const filters = Array.isArray(config.filters) ? config.filters : [];

  const endpoint = apiEndpoint(`output/${task}`);
  const previewData = sampleRow || columns.reduce((acc, col) => ({ ...acc, [col]: col }), {});
  const searchConfig = config.search || { enabled: false, columns: [] };

  return (
    <Box>
      <Typography variant="h5" gutterBottom>タスク設定: {task}</Typography>
      <Box sx={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        <Box sx={{ display: 'flex', flexDirection: 'column', maxWidth: 720, gap: 2 }}>
          <TextField
            select
            fullWidth
            label="テーブル"
            value={config.table}
            onChange={e => setConfig(cfg => ({
              ...cfg,
              table: e.target.value,
              filters: [],
              search: { ...cfg.search, columns: [] }
            }))}
          >
            <MenuItem value="">選択</MenuItem>
            {tables.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
          </TextField>
          <TextField
            select
            fullWidth
            label="形式"
            value={config.format}
            onChange={e => setConfig(cfg => ({ ...cfg, format: e.target.value }))}
          >
            <MenuItem value="html">HTML</MenuItem>
            <MenuItem value="json">JSON</MenuItem>
          </TextField>
        {config.format === 'html' && (
          <>
            {columns.length > 0 ? (
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 1 }}>
                {columns.map(c => (
                  <Box key={c} sx={{ px: 1, py: 0.5, border: '1px solid', borderColor: 'grey.400', borderRadius: 1 }}>
                    {c}
                  </Box>
                ))}
              </Box>
            ) : (
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                テーブルを選択すると利用可能なカラムが表示されます。
              </Typography>
            )}
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
              <Box sx={{ border: '1px solid', borderColor: 'grey.300', borderRadius: 1, p: 1.5, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                <FormControlLabel
                  control={(
                    <Checkbox
                      checked={!!searchConfig.enabled}
                      onChange={e => setConfig(cfg => ({
                        ...cfg,
                        search: {
                          enabled: e.target.checked,
                          columns: e.target.checked ? cfg.search?.columns || [] : []
                        }
                      }))}
                      disabled={columns.length === 0}
                    />
                  )}
                  label="検索欄を追加する"
                />
                {searchConfig.enabled && (
                  <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      検索対象にするフィールドを選択してください。
                    </Typography>
                    {columns.length === 0 ? (
                      <Typography variant="body2" color="text.secondary">
                        テーブルを選択すると検索対象を指定できます。
                      </Typography>
                    ) : (
                      <FormGroup sx={{ display: 'flex', flexDirection: 'row', flexWrap: 'wrap' }}>
                        {columns.map(column => {
                          const selected = Array.isArray(searchConfig.columns) && searchConfig.columns.includes(column);
                          return (
                            <FormControlLabel
                              key={`search-column-${column}`}
                              control={(
                                <Checkbox
                                  checked={selected}
                                  onChange={e => {
                                    const checked = e.target.checked;
                                    setConfig(cfg => {
                                      const current = Array.isArray(cfg.search?.columns) ? cfg.search.columns : [];
                                      const nextColumns = checked
                                        ? [...current.filter(col => col !== column), column]
                                        : current.filter(col => col !== column);
                                      return {
                                        ...cfg,
                                        search: {
                                          ...cfg.search,
                                          enabled: checked ? true : nextColumns.length > 0 && cfg.search?.enabled,
                                          columns: nextColumns
                                        }
                                      };
                                    });
                                  }}
                                />
                              )}
                              label={column}
                            />
                          );
                        })}
                      </FormGroup>
                    )}
                  </Box>
                )}
              </Box>
              {filters.length === 0 && (
                <Typography variant="body2" color="text.secondary">
                  フィルターは未設定です。「フィルターを追加」を押してタブを作成してください。
                </Typography>
              )}
              {filters.map((filter, index) => (
                <Box
                  key={filter.id || `filter-${index}`}
                  sx={{
                    border: '1px solid',
                    borderColor: 'grey.300',
                    borderRadius: 1,
                    p: 1.5,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1.5,
                    width: '100%'
                  }}
                >
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 2 }}>
                    <Typography variant="subtitle1" component="div">
                      フィルター {index + 1}
                    </Typography>
                    <Button color="error" size="small" onClick={() => removeFilter(filter.id)}>
                      削除
                    </Button>
                  </Box>
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5 }}>
                    <TextField
                      label="グループ名"
                      value={filter.label}
                      onChange={e => updateFilter(filter.id, { label: e.target.value })}
                      sx={{ minWidth: 200, flex: '1 1 200px' }}
                    />
                    <TextField
                      select
                      label="参照カラム"
                      value={filter.column}
                      onChange={e => setFilterColumn(filter.id, e.target.value)}
                      sx={{ minWidth: 200, flex: '1 1 200px' }}
                      disabled={columns.length === 0}
                    >
                      <MenuItem value="">未選択</MenuItem>
                      {columns.map(c => (
                        <MenuItem key={`${filter.id}-${c}`} value={c}>{c}</MenuItem>
                      ))}
                    </TextField>
                    <TextField
                      select
                      label="参照方法"
                      value={filter.type}
                      onChange={e => setFilterType(filter.id, e.target.value)}
                      sx={{ minWidth: 200, flex: '1 1 200px' }}
                    >
                      {FILTER_TYPES.map(option => (
                        <MenuItem key={`${filter.id}-type-${option.value}`} value={option.value}>{option.label}</MenuItem>
                      ))}
                    </TextField>
                  </Box>
                  {filter.type === 'date' && (
                    <TextField
                      label="日付フォーマット"
                      value={filter.dateFormat}
                      onChange={e => updateFilter(filter.id, { dateFormat: e.target.value })}
                      helperText="PHP の date フォーマット文字列 (例: Y-m, Y年n月)。"
                    />
                  )}
                  {filter.type === 'list' && (
                    <TextField
                      label="区切り文字"
                      value={filter.delimiter}
                      onChange={e => updateFilter(filter.id, { delimiter: e.target.value })}
                      helperText="データを複数の値に分割する際の区切り文字 (例: ,)。"
                    />
                  )}
                  <TextField
                    label="タブラベルテンプレート"
                    value={filter.labelTemplate}
                    onChange={e => updateFilter(filter.id, { labelTemplate: e.target.value })}
                    helperText="{{value}} を含めるとタブに値を埋め込めます。空欄の場合は値そのものが表示されます。"
                  />
                  <TextField
                    select
                    label="並び順"
                    value={filter.sort}
                    onChange={e => updateFilter(filter.id, { sort: e.target.value })}
                    sx={{ minWidth: 200, maxWidth: 260 }}
                  >
                    {SORT_OPTIONS.map(option => (
                      <MenuItem key={`${filter.id}-sort-${option.value}`} value={option.value}>{option.label}</MenuItem>
                    ))}
                  </TextField>
                </Box>
              ))}
              <Box sx={{ pt: 0.5 }}>
                <Button variant="outlined" onClick={addFilter} disabled={!config.table || columns.length === 0}>
                  フィルターを追加
                </Button>
              </Box>
            </Box>
          </>
        )}
        {config.format === 'html' && (
          <>
            <TextField
              label="フィルターCSS"
              multiline
              minRows={3}
              value={config.filterCss}
              onChange={e => setConfig({ ...config, filterCss: e.target.value })}
              helperText="フィルター表示をカスタマイズする CSS を入力できます。"
            />
            <TextField
              label="CSS"
              multiline
              minRows={4}
              value={config.css}
              onChange={e => setConfig({ ...config, css: e.target.value })}
            />
            <TextField
              label="HTML"
              multiline
              minRows={4}
              value={config.html}
              onChange={e => setConfig({ ...config, html: e.target.value })}
            />
            <Typography variant="body1" sx={{ mb: 2, fontSize: '1rem' }}>
              ショートコード: [reactdb_output task="{task}"]
            </Typography>
          </>
        )}
        {config.format === 'json' && (
          <Box sx={{ mb: 2 }}>エンドポイント: {endpoint}</Box>
        )}
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button variant="contained" onClick={handleSave}>保存</Button>
          <Button color="error" variant="outlined" onClick={handleDelete}>削除</Button>
        </Box>
        </Box>
        {config.format === 'html' && (
          <HTMLPreview html={config.html} css={config.css} filterCss={config.filterCss} data={previewData} />
        )}
      </Box>
    </Box>
  );
};

export default OutputTask;
