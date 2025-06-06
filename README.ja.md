# WordPress向けReact DBプラグイン

このリポジトリは、Reactアプリケーションを管理画面に組み込むシンプルなWordPressプラグインを提供します。Reactアプリは独自のREST APIエンドポイントを通じてCSVファイルの読み書きや、操作ログテーブルへの記録を行います。

## はじめに

1. JavaScript依存パッケージをインストールします。

```bash
npm install
```

2. Reactアプリケーションをビルドします。

```bash
npm run build
```

ビルド後、`package.json`で定義されているスクリプトにより、生成物は`react-db-plugin/assets`に`app.js`と`app.css`としてコピーされます。

3. `react-db-plugin`ディレクトリをWordPressの`wp-content/plugins`に移動し、管理画面で**React DB Plugin**を有効化します。

有効化すると「React DB」というメニューが追加されるほか、フロントエンド用のページ`/react-db-app/`が自動生成されます。管理画面を開かずにこのURLからReactアプリにアクセスできます。

有効化時には操作ログを保存する `wp_reactdb_logs` テーブルも作成されます。プラグインをアンインストールすると、このテーブルと `react-db-app` ページは自動的に削除されます。

CSVのインポートでは最初の100行を解析し、各カラムの値に応じて整数、数値、日時、テキストの型を自動的に設定したテーブルが生成されます。

もしページが作成されていない場合は、新規ページを作成して`[reactdb_app]`ショートコードを挿入してください。

## ショートコードとブロック

データベーステーブルの1行を表示するには、`[reactdb]`ショートコードまたは**React DB Block**を使用します。どちらも`DB:"テーブル",追加データ`という形式の`input`属性を受け取ります。例:

```wordpress
[reactdb input='DB:"c1",sample']
```

## 出力API

プラグインで設定したタスクのデータは REST API から取得できます。
`/wp-json/reactdb/v1/output/<タスク名>` に GET リクエストを
送信してください。

```bash
curl -X GET https://example.com/wp-json/reactdb/v1/output/testAPI-JSON
```

上記の例では `testAPI-JSON` タスクで定義されたテーブル内容が
JSON 形式で返されます。

## 開発メモ

- Reactのソースコードは`src/`に配置されています。
- PHPプラグインファイルは`react-db-plugin/`にあります。
- 画面レイアウト案は`設計.txt`に記載されています。

## プラグイン情報の変更

プラグインのバージョンや製作者名などを変更したい場合は、以下のファイルを編集します。

1. `react-db-plugin/react-db-plugin.php` の冒頭にあるコメントブロックには、`Version:` や `Author:` などのメタ情報が記載されています。ここを書き換えると WordPress 上で表示される情報が更新されます。
2. React アプリ側のバージョン番号は `package.json` の `"version"` フィールドで管理されています。値を変更した後は `npm run build` を実行し、ビルド結果を再度 `react-db-plugin/assets` にコピーしてください。

## テスト

環境に必要な依存関係がインストールされている場合、次のコマンドでテストを実行できます。

```bash
npm test
```
