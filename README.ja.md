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

## 開発メモ

- Reactのソースコードは`src/`に配置されています。
- PHPプラグインファイルは`react-db-plugin/`にあります。
- 画面レイアウト案は`設計.txt`に記載されています。

## テスト

環境に必要な依存関係がインストールされている場合、次のコマンドでテストを実行できます。

```bash
npm test
```
