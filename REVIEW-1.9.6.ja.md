# React DB Plugin 1.9.6：管理者での権限エラーへの対応

## 確認できた不具合

前版のフロントエンドは、WordPressのJSONエラーではないHTTP 403（WAFやプロキシのHTMLエラーなど）にも「manage_optionsが必要」と表示していました。その文言だけではWordPressの実際の権限不足とは断定できません。

また、フロントページに埋め込まれたnonceを固定使用していたため、キャッシュ済みページや期限切れ認証情報を扱いにくい状態でした。

## 追加で判明したビルドの問題

以前の梱包処理は、build/static/js内で最初に見つかったmain*.jsを選んでいました。古いビルドが残る場合、新しいコードがZIPへ入らないことを今回のビルドで確認しました。asset-manifest.jsonが指定する生成物だけをコピーする方式へ変更し、古いファイルが残るケースの2テストと、実際のZIP内の一致確認を追加しました。

## 修正

- 非WordPress形式の403を、WordPressの権限不足と区別して表示します。WordPressが返した固有の拒否理由も維持します。
- 管理画面の起動前に、WordPress標準の `admin-ajax.php?action=rest-nonce` でCookieに基づくREST nonceの更新を試みます。
- admin-ajaxが制限されていても、既存のREST nonceが有効なら操作を続けられます。
- 現在の認証ユーザー自身だけの状態を返す `GET /reactdb/v1/auth/status` を追加しました。WordPressのCookie認証・nonce検証を通ったログイン済みユーザーのみ利用できます。
- API側のユーザーID・ログイン名・ロール・サイトID・manage_optionsの実効値を表示します。
- ページとAPIのユーザー／サイトが異なる場合は、操作を止めてキャッシュ・URLの確認を案内します。
- 管理者ロール定義の欠落、ユーザー個別の拒否、実効権限への制限を分けて案内します。
- アプリページにはキャッシュ禁止ヘッダーとDONOTCACHEPAGEを設定します。WordPressが起動する前に配信されるCDN等の既存キャッシュは別途削除が必要な場合があります。
- 認証の再確認中や失敗時も、それまでの編集内容をアンマウントせず保持します。

管理APIの `manage_options` 判定は維持しています。ロール名がadministratorという理由だけで許可したり、WordPressのロール設定やユーザー権限を書き換えたりはしません。

## 更新・切り分け

1. プラグインを削除せず、1.9.6のZIPで上書き更新してください。
2. アプリページをCtrl+F5で再読込してください。
3. 拒否される場合は、画面上部の「認証診断」にある `reason` と `canManageOptions` を確認してください。

| reason | 診断 |
| --- | --- |
| allowed | APIがmanage_optionsを認めています。保存だけが403になる場合は、表示されるエラー形式やサーバーのWAFログを確認します。 |
| administrator_role_incomplete | administratorロールにmanage_optionsの付与がありません。移行先のロール定義の確認が必要です。 |
| user_capability_denied | ユーザーの個別権限でmanage_optionsが拒否されています。 |
| capability_filtered | 基本の権限情報には付与がありますが、実効判定で拒否されています。権限制御プラグイン等を確認します。 |
| capability_missing | このサイトで必要な権限がありません。所属サイトとロール割当を確認します。 |

## 検証と限界

Reactの28テスト、梱包処理の2テスト、PHPの27チェック、PHP 8.3で14ファイルの構文検査、本番ビルド、ZIPの整合性を確認しました。PHPテストはWordPress関数とDBを制御した回帰テストです。稼働サイトのユーザー設定、WAFログ、実WordPress／MySQL環境は直接確認していません。現時点で利用環境固有の拒否原因が特定できたわけではありません。

## 公式資料

- [REST APIのCookie認証とnonce](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [current_user_can：実効権限による判定](https://developer.wordpress.org/reference/functions/current_user_can/)
- [WordPress標準のREST nonce更新処理](https://developer.wordpress.org/reference/functions/wp_ajax_rest_nonce/)
