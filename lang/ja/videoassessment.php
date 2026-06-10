<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['Reembedthelink'] = 'リンクを再埋め込み';
$string['accepteddifference'] = 'スコアの許容差';
$string['accepteddifference_help'] = 'スコアの許容差。デフォルト20%。ここでは、学生のスコアの許容範囲または「差」を、教師が事前に入力したスコアと比較して設定できます。学生のスコアがルーブリックの任意の基準で許容差外にある場合、トレーニング事前テストに不合格となり、再受験する必要があります。';
$string['additionalassessmentbyteacher'] = '教師による追加評価';
$string['addmember'] = 'メンバーを追加';
$string['addpeer'] = 'ピアを追加...';
$string['addpeergroup'] = 'ピアグループを追加';
$string['addprefixtolabel'] = 'ラベル名にプレフィックスを追加';
$string['addsuffixtolabel'] = 'ラベル名にサフィックスを追加';
$string['admin_settings_executable_locked'] = 'この実行可能ファイルパスはサイト管理者によりロックされており($CFG->preventexecpath = true)、本フォームから変更できません。';
$string['advancedgradingmethodsgroup'] = '評価方法';
$string['advancedgradingmethodsgroup_help'] = 'ビデオアセスメントでは、「評価方法」のデフォルト設定を変更しないでください。すべての設定でルーブリックを使用します。これはパフォーマンス評価の最良の方法だからです。設定を変更すると、ビデオアセスメントシステムが動作しなくなる可能性があります。';
$string['after'] = '後';
$string['afterduedateevery'] = '締切日後、毎';
$string['aftergrade'] = '後評価';
$string['afterlabel'] = '後';
$string['aftermarks'] = '後スコア';
$string['afterpeer'] = '後 - ピア';
$string['afterself'] = '後 - 自己';
$string['afterteacher'] = '後 - 教師';
$string['aftervideo'] = '後ビデオ';
$string['allowexternallinks'] = '外部動画リンクを許可(例: YouTube)';
$string['allowexternallinks_help'] = 'サイト全体で有効にすると、各活動の管理者は学生・教師に対して外部にホストされた動画(YouTube、Vimeo、esup-portail/Pod 等)の URL 貼り付けを許可できます。';
$string['allowstudentpeerselection'] = '学生がピアを選択できるようにする';
$string['allowstudentpeerselection_help'] = '有効にすると、学生は自分でピアパートナーを選択できます。';
$string['allowstudentupload'] = '学生がビデオをアップロードできる';
$string['allowstudentupload_help'] = '有効にすると、学生はビデオを1つずつアップロードできます。一括アップロードは教師のみ利用可能です。';
$string['allowvideorecording'] = '動画の録画を許可';
$string['allowvideorecording_help'] = 'サイト全体で有効にすると、各活動の管理者は学生・教師にブラウザでの直接録画機能を許可できます。';
$string['allowvideouploads'] = '動画ファイルのアップロードを許可';
$string['allowvideouploads_help'] = 'サイト全体で有効にすると、各活動の管理者は学生・教師に動画ファイルの直接アップロードを許可できます。';
$string['allowyoutube'] = '外部動画リンクを許可(例: YouTube)';
$string['allowyoutube_help'] = '有効にすると、学生と教師は YouTube・Vimeo・esup-portail/Pod など外部にホストされた動画の URL を貼り付けて評価対象にできます。';
$string['allparticipants'] = 'すべての参加者';
$string['allscores'] = '</span><span class="red">自己、</span> <span class="blue">ピア、</span> <span class="green">教師、</span> <span class="orange">クラス</span>スコア';
$string['assess'] = '評価';
$string['assess_help'] = '評価段階では、学生は高度な評価で設定されたルーブリックを使用して自己評価、ピア評価を行います。学生に「教師権限」を与えるか、教師が入力するためのルーブリックを紙で設計することで、学生にルーブリックを設計する権利を与えることも可能です。デフォルトでは、学生は自己評価を完了するまで教師の採点を見ることができません。その後、学生は教師の評価を閲覧できます。';
$string['assessagain'] = '【再評価】';
$string['assessedby'] = '評価者';
$string['assignclass'] = 'クラスを割り当て';
$string['assignclass_help'] = 'この機能により、教師は評価の「クラス」モードをオンまたはオフにできます。「クラス」モードは、すべての学生がライブのリアルタイムパフォーマンスを視聴して、録画なしで話者を評価するためのものです。時間的プレッシャーのため正確に評価するのは困難ですが、学生にルーブリックの使用と理解の練習を提供し、プレゼンテーションを半分聞くのではなく、積極的に学習し続けさせます。学生はクラスウェブサイトにログインし、コースで適切なビデオアセスメントアクティビティを見つける必要があります。パフォーマンスを行っている学生を検索し、各スケールでスコアを選択し始めます。すべての学生のスコアは、クラス全体の単一の「クラス」スコアのために平均化され、高すぎるまたは低すぎるスコアを軽減します。';
$string['assignmentisdue'] = 'ビデオアセスメントの締切';
$string['assignpeers'] = 'ピアを割り当て';
$string['assignpeers_help'] = 'ピア評価のために0〜3人のピアを割り当てることができます。デフォルトは1人のピアです。ピアを割り当てる方法は3つあります：1）コース全体でランダム、2）グループ内でランダム、3）手動。ランダム割り当ての両方の方法は、自動割り当て後に手動で調整できます。';
$string['assignpeersrandomly'] = 'ピアをランダムに割り当て';
$string['associate'] = '関連付け';
$string['associate_help'] = 'ファイルをアップロードした後、各ビデオファイルは正しいパフォーマンス学生に関連付ける必要があります。ファイルは、コース内の学生のMoodleログイン名（ユーザー）を選択することでマッチングされます。これはプロセスの「関連付け」段階と呼ばれます（「アップロード」の後、「評価」の前）。この画面では、ドロップダウンメニューにコース（またはコースのセクション）内のすべての学生（ユーザー）がリストされます。';
$string['associated'] = '関連付け済み';
$string['associations'] = '関連付け';
$string['availabledate'] = '利用可能日';
$string['average'] = '平均';
$string['backupdefaults'] = 'バックアップデフォルト';
$string['backupusers'] = 'ユーザデータを含む';
$string['backupusersdesc'] = 'バックアップにユーザデータ (ビデオ、評定) を含むかどうか、デフォルトを設定します。';
$string['before'] = '前';
$string['beforeafter'] = '前/後';
$string['beforeclass'] = 'クラス';
$string['beforeduedate'] = '締切日前';
$string['beforegrade'] = '前評価';
$string['beforelabel'] = '前';
$string['beforemarks'] = '前スコア';
$string['beforepeer'] = 'ピア';
$string['beforeself'] = '自己';
$string['beforeteacher'] = '教師';
$string['beforetraining'] = 'トレーニング事前テスト';
$string['beforevideo'] = '前ビデオ';
$string['bonuspercentage'] = 'ボーナスパーセンテージ';
$string['bulkvideoupload'] = '一括ビデオアップロード';
$string['calendardue'] = '{$a}の締切';
$string['calendargradingdue'] = '{$a}の評価締切';
$string['changetraingingwarning'] = 'トレーニング変更警告';
$string['changeuploadtype'] = 'アップロードタイプを変更';
$string['class'] = 'クラス';
$string['classassessments'] = 'クラス評価';
$string['classgrading'] = 'クラス全体評価';
$string['classgrading_help'] = 'クラス全体の学生がライブパフォーマンスを視聴し、リアルタイムで評価したい場合は、この機能を使用してください。クラス全体評価をオンにするには、「クラス評価を開く」をクリックしてください。デフォルトは「クラス評価を閉じる」です。すべての学生の成績が1つの平均成績に合計されます。';
$string['clickonthe'] = 'クリックしてください';
$string['close'] = 'クラス評価を閉じる';
$string['command_validator_disallowed_character'] = 'コマンドに許可されていない文字が含まれています。英数字、空白、および _ - . / = { } \' のみが使用可能です。';
$string['command_validator_empty'] = 'コマンドを空にすることはできません。';
$string['command_validator_forbidden_substring'] = 'コマンドに禁止された文字列が含まれています: {$a}。シェルのメタ文字は使用できません。';
$string['command_validator_input_placeholder'] = 'コマンドには {INPUT} プレースホルダーをちょうど1回含めてください。';
$string['command_validator_must_invoke_ffmpeg'] = 'コマンドは最初のトークンとして ffmpeg バイナリを呼び出す必要があります。';
$string['command_validator_must_invoke_mp4box'] = 'コマンドは最初のトークンとして MP4Box バイナリを呼び出す必要があります。';
$string['command_validator_output_placeholder'] = 'コマンドには {OUTPUT} プレースホルダーをちょうど1回含めてください。';
$string['confirmdeletegrade'] = 'この成績を削除してもよろしいですか？';
$string['confirmdeletevideos'] = '{$a}個のビデオを削除してもよろしいですか？';
$string['course'] = 'コース';
$string['coursemisconf'] = 'コース設定が正しくありません。';
$string['courseshortnameexist'] = '短縮名は既に他のコースで使用されています';
$string['currentgrade'] = '成績表の現在の成績';
$string['daysbefore'] = '日前';
$string['delayedteachergrade'] = '教師評価の遅延';
$string['delayedteachergrade_help'] = '「はい」を有効にすると、学生が自己評価を完了するまで、教師の評価は学生に表示されません。これにより、評価を開始する前に教師のスコアを見ることによる学生の採点の偏りを軽減します。';
$string['deleteselectedvideos'] = '選択したビデオを削除';
$string['deletevideo'] = 'ビデオを削除';
$string['deletevideos'] = '一括ビデオ削除';
$string['deletevideos_help'] = '教師はこの方法で複数のファイルを削除できます。';
$string['deletevideos_videos'] = 'ビデオ';
$string['deletevideos_videos_help'] = '選択されたすべてのビデオがアクティビティから削除されます。サーバー上のビデオデータはMoodle cronによってクリーンアップされます。';
$string['description'] = '説明';
$string['disassociate'] = '関連付け解除';
$string['diskspacetmpl'] = 'サーバーディスク容量: {$a->free} 空き / {$a->total} 合計';
$string['donotclickhere'] = 'ここをクリックしないでください。';
$string['downloadexcel'] = 'Excelで結果をダウンロード';
$string['dropvideofileshere'] = 'ビデオファイルをここにドロップ';
$string['duedate'] = '締切日';
$string['duplicateerrors'] = '複製エラー';
$string['duplicatefor'] = '複製先';
$string['duplicaterubric'] = 'ルーブリックを複製';
$string['duplicaterubric_help'] = 'この機能は、教師用に作成されたルーブリックを繰り返し、ルーブリックを自己、ピア、クラスモードの評価に複製します。';
$string['duplicatesuccess'] = '複製成功';
$string['educatornote_landscape'] = '教員の皆さまへ: 最良の結果を得るため、学習者にはスマートフォンを横向き（ランドスケープ）にして録画するよう案内してください。';
$string['errorcapturingmedia'] = 'メディアキャプチャエラー：';
$string['errorcheckvideostodelete'] = '削除するビデオをチェックしてください。';
$string['errorinvalidtiming'] = '無効なタイミング値';
$string['errornovideorecord'] = 'まずビデオ記録を追加してください';
$string['errorovermaximumpossiblegrade'] = '合格する成績は最大可能成績100を超えることはできません';
$string['erroruploadvideo'] = 'ビデオをアップロードしてください';
$string['event_grade_assigned'] = '成績が付与されました';
$string['event_peer_review_submitted'] = 'ピアレビューが送信されました';
$string['event_report_viewed'] = 'レポートが閲覧されました';
$string['event_video_uploaded'] = 'ビデオがアップロードされました';
$string['existingcourse'] = '既存のコースに公開';
$string['existingcourse_help'] = '（新規）以外に設定すると、ビデオは選択されたコースに公開されます。コースにリソースを追加する権限が必要です。';
$string['existingcourseornewcourse'] = '既存のコースまたは新しいコースに公開';
$string['failed'] = '不合格';
$string['failednotice'] = '申し訳ありません。いくつかのスコアが標準スコアから{$a->accepteddifference}%異なっていました。すべて「○」でなければなりません、「×」はありません。<br />{$a->button}';
$string['fairnessbonus'] = '公平性ボーナス';
$string['feedback'] = 'フィードバック';
$string['feedbackfrom'] = '{$a}からのフィードバック';
$string['ffmpegcommand'] = 'FFmpegコマンド';
$string['ffmpegcommanddesc'] = 'プレースホルダー付きFFmpegコマンドライン: {INPUT} {OUTPUT}';
$string['ffmpegthumbnailcommand'] = 'FFmpegサムネイルコマンド';
$string['ffmpegthumbnailcommanddesc'] = 'プレースホルダー付きFFmpegコマンドライン: {INPUT} {OUTPUT}、画像出力オプション付き';
$string['filedeleted'] = 'ファイルが削除されました。';
$string['fileuploadlinks'] = 'ファイルアップロード / リンク';
$string['finalscore'] = '最終スコア';
$string['finalscorefortable'] = '最終スコア';
$string['finishmakingrubric'] = 'ルーブリック編集を終えて評価画面へ';
$string['firstassess'] = '【初回評価】';
$string['firstassessmentbystudent'] = '学生による初回評価';
$string['firstassessmentbyteacher'] = '教師による初回評価';
$string['fullnamecourse'] = 'コース正式名';
$string['gdprcookiesuppression'] = 'トラッキングCookieを抑制する（GDPR）';
$string['gdprcookiesuppression_help'] = '有効にすると、外部動画はプライバシー強化ホスト（YouTubeはyoutube-nocookie.com、VimeoはDo-Not-Trackパラメータ）経由で埋め込まれ、学習者が再生を開始するまで動画サービスがトラッキングCookieを設定しません。GDPR遵守のため推奨します。';
$string['generalcomments'] = '一般的なコメント';
$string['grade'] = '評価';
$string['grade_grading_name'] = '採点';
$string['grade_help'] = 'このセクションは、自己/ピア/クラス/教師の成績を組み合わせる設定用です。1つの組み合わせた成績がこのクラスの成績表にアップロードされます。評価ページに移動してダウンロードリンクを見つけることで、スコア詳細をExcel形式で分析・ダウンロードできます。さらに、このセクションには自己評価を改善するための事前校正と公平性ボーナスの設定があります。';
$string['grade_rating_name'] = '評価';
$string['gradecategory'] = '成績カテゴリ';
$string['graded'] = '評価済み';
$string['gradeitem:beforeclass'] = 'クラス';
$string['gradeitem:beforepeer'] = 'ピア';
$string['gradeitem:beforeself'] = '自己';
$string['gradeitem:beforeteacher'] = '教師';
$string['gradeitem:beforetraining'] = 'トレーニング事前テスト';
$string['gradingareadefined'] = 'ルーブリックが既に存在するため複製できません';
$string['group'] = 'グループ';
$string['inputnewcoursename'] = '新しいコース名を入力';
$string['inputnewcourseshortname'] = '新しいコース短縮名を入力';
$string['insertintosection'] = 'セクションに挿入';
$string['installerrorffmpegdoesnotexist'] = 'ffmpegのデフォルトインストールパスが存在しません！';
$string['installerrorffmpegversionnotfound'] = 'ffmpegのバージョン番号が見つかりませんでした。ffmpegがシステムに正しくインストールされ、正常に動作しているか確認してください。';
$string['installsuccessffmpeg'] = 'ffmpegが正常に検出されました: {$a}';
$string['invalidid'] = '無効なID';
$string['invaliduploadedfile'] = 'アップロードされたファイルが無効です。';

$string['latesubmissionsaccepted'] = '{$a}まで許可';
$string['level'] = 'レベル';
$string['liststudents'] = '学生一覧';
$string['loading'] = '読み込み中...';
$string['managegrades'] = '成績管理';
$string['manageuploadedvideos'] = 'アップロードされたビデオを管理';
$string['managevideo'] = 'ビデオを管理';
$string['managevideos'] = 'ビデオを管理';
$string['managevideos_help'] = '「ビデオ管理」管理ページには9つの機能があります。デフォルト設定を変更したい場合を除き、どの機能にも触れる必要はありません。
<br />a. ビデオをアップロード
<br />b. 一括ビデオアップロード
<br />c. 一括ビデオ削除
<br />d. 関連付け
<br />e. 評価
<br />f. ピアを割り当て
<br />g. ビデオを公開
<br />h. クラスを割り当て
<br />i. ルーブリックを複製';
$string['markasreadonnotificationno'] = '通知は自動的に既読としてマークされません。';
$string['markasreadonnotificationyes'] = '通知は自動的に既読としてマークされます。';
$string['maximumpoints'] = '最大ポイント';
$string['mobilequickmail'] = 'モバイルクイックメール';
$string['modgrade'] = '成績タイプ';
$string['modgrade_help'] = 'ビデオアセスメントでは、「成績タイプ」のデフォルト設定を変更しないでください。成績タイプは「ポイント」で、最大成績は「100」です。設定を変更すると、ビデオアセスメントシステムが動作しなくなる可能性があります。';
$string['modulename'] = 'ビデオアセスメント';
$string['modulenameplural'] = 'ビデオアセスメント';
$string['mp4boxcommand'] = 'MP4Boxコマンド';
$string['mp4boxcommanddesc'] = 'MP4ビデオのプログレッシブ再生を可能にするMP4Boxコマンド';
$string['myvideos'] = '私のビデオ';
$string['namesort'] = '名 / 姓';
$string['no'] = 'いいえ';
$string['nomoresubmissionsaccepted'] = '延長が許可された参加者のみ許可';
$string['nopeergroup'] = 'まだピアグループがありません';
$string['notattempted'] = '未試行';
$string['notext'] = 'テキストなし';
$string['notgradedyet'] = 'まだ評価されていません。';
$string['notificationcarriergroup'] = '通知キャリア';
$string['notificationcarriergroup_help'] = '通知には2つの選択肢があります：サイトに登録されたMoodleメールアドレス、またはモバイルクイックメール（携帯電話のメールアドレスを使用するためのオプションブロック）。1つまたは両方を選択できます。';
$string['notificationcontenttypegroup'] = '通知内容';
$string['notificationmessagesent'] = '通知メッセージが送信されました';
$string['notifications'] = '通知';
$string['notifications_help'] = '通知は評価情報を学生のメール受信箱またはモバイルクイックメールアドレスに送信します。4種類の通知があります：
<br />a. 教師コメント通知
<br />b. ピアコメント通知
<br />c. リマインダー通知
<br />d. ビデオアップロード/再アップロード通知';
$string['notificationssendtype'] = '通知キャリア';
$string['notsupportedbrowser'] = 'このブラウザはサポートされていません';
$string['novideo'] = 'ビデオなし';
$string['numberofpeers'] = 'ピア数';
$string['offairnessbonus'] = '公平性ボーナスの';
$string['ofteacherscore'] = '教師スコアの = ';
$string['onduedate'] = '締切日';
$string['onpeerassessment'] = 'ピア評価時';
$string['onselfassessment'] = '自己評価時';
$string['onselfassessmentwithcomments'] = '20語のコメント付き自己評価時';
$string['ontopoftotal'] = '合計の上に';
$string['onvideouploaded'] = 'ビデオアップロード時';
$string['open'] = 'クラス評価を開く';
$string['operations'] = '操作';
$string['or'] = 'または';
$string['order'] = '順序';
$string['orderasc'] = '昇順';
$string['orderdesc'] = '降順';
$string['originalname'] = '元の名前';
$string['passed'] = '合格';
$string['passednotice'] = 'おめでとうございます！すべてのスコアが標準スコアに近いです！<br />{$a}評価に進んでください。';
$string['path'] = 'パス';
$string['pause'] = '一時停止';
$string['peer'] = 'ピア';
$string['peerassessments'] = 'ピア評価';
$string['peercomentnotificationlabel'] = 'ピアコメント通知';
$string['peerfairnessbonus'] = 'ピア公平性ボーナス';
$string['peerfairnessbonus_help'] = 'ピア公平性ボーナスは、「公平に」スコアを付ける学生、つまり、すべてのスコアが「100」や「0」ではなく、教師が採点しているものにかなり近いスコアを付ける学生に報酬を与えます。このツールの設定オプションには、最終スコアの何%をボーナスとして割り当てるかを決定し、教師のスコアへの近さに基づいて学生がそのボーナスの何%を受け取るかを決定することが含まれます。';
$string['peerfairnessbonusfortable'] = '+ピア公平性<br>ボーナス';
$string['peergroup'] = 'ピアグループ';
$string['peerratings'] = 'ピア評価';
$string['peers'] = 'ピア';
$string['peertnotificationtemplate'] = '[[学生名]]さんへ、
お疲れ様です！クラスメートの一人があなたのプレゼンテーションビデオを確認し、いくつかのスコアとコメントを作成しました。以下がそれらです：
[[課題名を挿入]] [[現在の日付を挿入]]
このレポートへのリンクです：[[評価を表示する学生ページへのリンクを挿入]]
**クラスメートは公平にスコアを付けるとボーナスを受け取ります**
質問があれば私にメールを送ってください [[教師メールアドレス]]
よろしくお願いします、
[[教師名]]';
$string['pleasechoosegradingareas'] = '評価エリアを選択してください';
$string['pleasechoosevideos'] = 'ビデオを選択してください';
$string['pleasedefinerubricforteacher'] = '教師用のルーブリックを定義してください';
$string['pluginadministration'] = 'ビデオアセスメント管理';
$string['pluginname'] = 'ビデオアセスメント';
$string['preventlate'] = '遅延提出を防ぐ';
$string['previewvideo'] = 'ビデオをプレビュー';
$string['printreport'] = 'レポートを印刷';
$string['printrubrics'] = 'すべてのルーブリックレポートを印刷';
$string['printview'] = '印刷ビューを開く';
$string['privacy:metadata:videoassessment'] = 'ビデオアセスメントファイルに関する情報。';
$string['privacy:metadata:videoassessment:class'] = 'クラスの数。';
$string['privacy:metadata:videoassessment:course'] = 'コースID番号。';
$string['privacy:metadata:videoassessment:intro'] = 'ファイルに関する詳細。';
$string['privacy:metadata:videoassessment:name'] = 'コースの名前。';
$string['privacy:metadata:videoassessment:ratingpeer'] = 'ピアによる評価。';
$string['privacy:metadata:videoassessment:ratingself'] = 'ユーザー自身による評価。';
$string['privacy:metadata:videoassessment:ratingteacher'] = '先生による評価。';
$string['privacy:metadata:videoassessment:timemodified'] = '最終変更時刻。';
$string['privacy:metadata:videoassessment:trainingdesc'] = 'トレーニングについての説明。';
$string['privacy:metadata:videoassessment_aggregation'] = 'ビデオアセスメントの集計に関する情報。';
$string['privacy:metadata:videoassessment_aggregation:timemodified'] = '最終更新時刻。';
$string['privacy:metadata:videoassessment_aggregation:timing'] = 'ビデオアセスメントの集約時間。';
$string['privacy:metadata:videoassessment_aggregation:userid'] = 'このビデオアセスメント集約の対象となるユーザー。';
$string['privacy:metadata:videoassessment_aggregation:videoassessment'] = 'ビデオアセスメントID。';
$string['privacy:metadata:videoassessment_grade_items'] = 'グレード一覧。';
$string['privacy:metadata:videoassessment_grade_items:gradeduser'] = '評価するユーザー。';
$string['privacy:metadata:videoassessment_grade_items:type'] = 'グレード名またはグレードの種類。';
$string['privacy:metadata:videoassessment_grade_items:videoassessment'] = 'ビデオアセスメントID。';
$string['privacy:metadata:videoassessment_grades'] = 'ビデオに関する評価記録。';
$string['privacy:metadata:videoassessment_grades:grade'] = 'グレード番号。';
$string['privacy:metadata:videoassessment_grades:gradeitem'] = 'グレーディング ID';
$string['privacy:metadata:videoassessment_grades:submissioncomment'] = '成績についてのコメント。';
$string['privacy:metadata:videoassessment_grades:timemarked'] = 'グレーディングエントリー時間。';
$string['privacy:metadata:videoassessment_grades:videoassessment'] = 'ビデオアセスメントID。';
$string['privacy:metadata:videoassessment_peers'] = 'ピアパートナー情報。';
$string['privacy:metadata:videoassessment_peers:peerid'] = 'ピア ID。';
$string['privacy:metadata:videoassessment_peers:userid'] = 'ピアパートナーユーザーID。';
$string['privacy:metadata:videoassessment_peers:videoassessment'] = 'ビデオアセスメントID。';
$string['privacy:metadata:videoassessment_sort_items'] = '並べ替え項目のリスト。';
$string['privacy:metadata:videoassessment_sort_items:itemid'] = 'アイテムIDを並べ替える。';
$string['privacy:metadata:videoassessment_sort_items:type'] = '並べ替え項目の種類。';
$string['privacy:metadata:videoassessment_sort_order'] = '並べ替え項目の並べ替え順序。';
$string['privacy:metadata:videoassessment_sort_order:sortitemid'] = '並べ替え項目のID。';
$string['privacy:metadata:videoassessment_sort_order:userid'] = 'この並べ替え可能なアイテムの対象者。';
$string['privacy:metadata:videoassessment_video_assocs'] = 'ビデオ課題。';
$string['privacy:metadata:videoassessment_video_assocs:associationid'] = 'この動画に関連するユーザー。';
$string['privacy:metadata:videoassessment_video_assocs:timemodified'] = '最終更新日時。';
$string['privacy:metadata:videoassessment_video_assocs:videoassessment'] = 'ビデオアセスメントID。';
$string['privacy:metadata:videoassessment_video_assocs:videoid'] = 'ビデオストレージID。';
$string['privacy:metadata:videoassessment_videos'] = 'アップロードされた動画に関する情報。';
$string['privacy:metadata:videoassessment_videos:filename'] = 'ビデオファイルのサーバー名。';
$string['privacy:metadata:videoassessment_videos:filepath'] = 'ビデオファイルのパス。';
$string['privacy:metadata:videoassessment_videos:originalname'] = 'アップロードされたビデオファイルの名前。';
$string['privacy:metadata:videoassessment_videos:timecreated'] = 'ファイルのアップロード時刻。';
$string['privacy:metadata:videoassessment_videos:timemodified'] = 'ファイルの最終変更時刻。';
$string['privacy:metadata:videoassessment_videos:videoassessment'] = 'ビデオアセスメントID。';
$string['publishvideos'] = 'ビデオを公開';
$string['publishvideos_help'] = 'この段階では、すべてのパフォーマンスが評価された後、教師は長期保存のためにビデオを選択できます。これらのビデオは、サイト内の別の新しく作成されたコースに公開されます。';
$string['publishvideos_videos'] = 'ビデオ';
$string['publishvideos_videos_help'] = '選択されたビデオは既存のコースまたは新しいコースに公開されます。';
$string['publishvideostocourse'] = 'コースにビデオを公開';
$string['quickSetup'] = 'クイックセットアップ';
$string['quickSetup_help'] = 'クイックセットアップ';
$string['ratingclass'] = 'クラス評価';
$string['ratingclass_help'] = 'この評価はビデオ録画では使用されませんが、すべてのクラスメートがスコアを付け、コメントを提供するライブパフォーマンス用です。通常は0%で、オンにしても、クラス全体の評価の目的は聴衆を忙しくさせ、ルーブリックを学習させることです。評価メニューでオンにする必要があります。レポートでは、クラス平均スコアは通常「黄色」で表示されます。';
$string['ratingpeer'] = 'ピア重み付け';
$string['ratingpeer_help'] = '学生の総合成績におけるピア評価の重み付けを設定します。複数のピアが学生を評価でき（最大3人）、ピアの平均スコアが表示されます。レポートでは、ピアのスコアは通常「青」色で表示されます。';
$string['ratings'] = '評価';
$string['ratings_help'] = '自己/ピア/クラス/教師評価を組み合わせる際、教師は各評価タイプの重み付けを100%以内で設定できます。典型的な重み付けは教師80%、自己10%、ピア10%、クラス0%などです。パーセンテージの合計は100%でなければならず、そうでない場合は警告が表示されます。教師を唯一の評価者にしたい場合は、教師100%、自己0%、ピア0%、クラス0%のように設定してください。';
$string['ratingself'] = '自己重み付け';
$string['ratingself_help'] = '学生の総合成績における自己評価の重み付けを設定します。自己評価は他のスコアに影響されることが多いため、学生が自己評価を完了するまで教師のスコアは表示されません。自己スコアは通常「赤」色で表示されます。';
$string['ratingteacher'] = '教師重み付け';
$string['ratingteacher_help'] = '学生の総合成績における教師の評価の重み付けを設定します。複数の教師が学生を評価でき、教師の平均スコアが表示されます。教師が唯一の評価者である場合は、この設定を100%にし、他をすべて0%にしてください。教師のスコアは通常「緑」色で表示されます。';
$string['readyforuse'] = '使用準備完了';
$string['reallydeletevideo'] = 'このビデオを削除してもよろしいですか？';
$string['reallyresetallpeers'] = 'ピア割り当てをリセットしてランダムに再割り当てします。続行しますか？';
$string['recordnewvideo'] = '新しい動画を録画(最大録画時間: 2分)';
$string['recordradios'] = '新しいビデオを録画';
$string['recordradios_help'] = '新しいビデオを録画は、評価のためにビデオを直接録画するためのものです。
この機能は、コンピューターまたは携帯電話のカメラにアクセスしてビデオ録画を開始します。
対照的に、「ビデオファイルをアップロード」選択は、以前に録画したビデオを選択してアップロードできるように、ファイルの写真/ビデオライブラリに移動します。
<br/>*録画停止ボタンをクリックしてから、自動的にアップロードしてください*';
$string['registeredemail'] = '登録メール';
$string['remark'] = '備考';
$string['reminder_notifition_mail_cron'] = 'リマインダー通知メールcron';
$string['remindernotification'] = 'リマインダー通知';
$string['remindernotificationtemplate'] = '[[学生名]]さんへ、
プレゼンテーションを視聴し、確認しましたか？
締切日は6月x日です。以下がリンクです：
[[自己評価ページへのリンクを挿入]]
スコアと同様に、少なくとも3つのコメントを書くことを忘れないでください。
質問があれば私にメールを送ってください [[教師メールアドレス]]。ありがとうございます！
よろしくお願いします、
[[教師名]]';
$string['report'] = 'レポート';
$string['results'] = '結果';
$string['resumerecording'] = '録画再開';
$string['retakevideo'] = 'ビデオを再撮影';
$string['reuploadvideo'] = 'リンク済み / アップロード済み動画を差し替える';
$string['save'] = '保存';
$string['saveassociations'] = '関連付けを保存';
$string['score'] = 'スコア';
$string['scores'] = 'スコア';
$string['seereport'] = 'レポートを見る';
$string['self'] = '自己';
$string['selfassessments'] = '自己評価';
$string['selffairnessbonus'] = '自己公平性ボーナス';
$string['selffairnessbonus_help'] = '自己公平性ボーナスは、「公平に」スコアを付ける学生、つまり、すべてのスコアが「100」や「0」ではなく、教師が採点しているものにかなり近いスコアを付ける学生に報酬を与えます。このツールの設定オプションには、最終スコアの何%をボーナスとして割り当てるかを決定し、教師のスコアへの近さに基づいて学生がそのボーナスの何%を受け取るかを決定することが含まれます。';
$string['selffairnessbonusfortable'] = '+自己公平性<br>ボーナス';
$string['selfpeer'] = '自己 / ピア';
$string['selfratings'] = '自己評価';
$string['settotalratingtoahundredpercent'] = '4つの評価（教師 + 自己 + ピア + クラス）は100%でなければなりません。';
$string['shortnamecourse'] = 'コース短縮名';
$string['simpledirectgroup'] = '採点 - シンプルダイレクト';
$string['singlevideoupload'] = '単一ビデオアップロード';
$string['sortby'] = 'ソート方法';
$string['sortid'] = 'IDでソート';
$string['sortmanually'] = '手動でソート';
$string['sortname'] = '名前でソート';
$string['startrecoding'] = '録画開始';
$string['stoprecording'] = '録画停止';
$string['studentrubric'] = '学生ルーブリック';
$string['submission'] = '提出';
$string['submissionby'] = '{$a}による提出';
$string['tabgrading'] = '採点';
$string['tabvideo'] = '動画';
$string['takevideo'] = 'ビデオを撮影';
$string['teacher'] = '教師';
$string['teacherassesstraining'] = 'トレーニング事前テストを評価';
$string['teachercomentnotificationlabel'] = '教師コメント通知';
$string['teachercommentnotification'] = '通知内容';
$string['teachercommentnotification_help'] = 'a. 教師コメント通知は、教師がコメントを作成して評価を保存するたびに学生にメールを送信します。
<br />b. ピアコメント通知：ピアがコメントを作成して評価を保存するたびに学生にメール通知を送信します。
<br />c. リマインダー通知は、学生が課題を忘れたり遅れたりしたときに与えられます。
<br />d. ビデオアップロード/再アップロード通知は、ビデオがビデオアセスメントモジュールにアップロードまたは再アップロードされるたびに教師に通知を送信します。
各タイプの通知のメールメッセージ形式は教師によって設定される必要があります。';
$string['teachernotificationtemplate'] = '[[学生名]]さんへ、
お疲れ様です！プレゼンテーションビデオを確認し、いくつかのスコアとコメントを作成しました。以下がそれらです：
[[課題名を挿入]] [[現在の日付を挿入]]
このレポートへのリンクです：[[評価を表示する学生ページへのリンクを挿入]]
6月7日にプレゼンテーションをやり直して、より良い成績を得ることができます。
質問があれば私にメールを送ってください [[教師メールアドレス]]
よろしくお願いします、
[[教師名]]';
$string['teacherratings'] = '教師評価';
$string['teacherrubric'] = '教師ルーブリック';
$string['teacherselfpeer'] = '教師/自己/ピア/クラス';
$string['templatetextfornotification'] = '通知用テンプレートテキスト';
$string['timemarked'] = 'マークされた時刻';
$string['timing'] = 'タイミング';
$string['timinggrade'] = '{$a}評価';
$string['timinglabel'] = '前/後の単語';
$string['timinglabel_help'] = 'ここに単語を入力することで、「前」と「後」のラベルをカスタマイズできます。空白のままにすると、標準の「前」と「後」が使用されます。';
$string['timingscores'] = '{$a}スコア';
$string['total'] = '合計';
$string['totalgrade'] = '総合成績';
$string['totalscore'] = '合計スコア';
$string['training'] = 'トレーニング';
$string['trainingdesc'] = 'トレーニング説明';
$string['trainingdesc_help'] = '学生に採点方法と各ルーブリック基準での教師スコアからの許容差を教える説明を追加してください。学生は合格するためにすべて「○」（教師スコアからの許容差内）を受け取る必要があります。';
$string['trainingdeschelp'] = 'トレーニング説明テキスト';
$string['trainingdesctext'] = 'このトレーニングに合格するには、あなた（赤いスコア）は各スケールを教師のスコア（緑のスコア）のxx%以内で評価する必要があります。xx%以下であれば「○」を受け取ります。xx%を超えると「×」を受け取ります。合格するにはすべてのスケールが「○」でなければなりません。';
$string['trainingpretest'] = 'トレーニング事前テスト';
$string['trainingpretest_help'] = 'テストの採点の「校正」と同様に、このトレーニング事前テスト機能は、学生が実際の採点に進む前に、まずトレーニングテストに合格することを強制します。学生は教師が提供するアップロードされたビデオとルーブリックを視聴します。教師が事前に入力した希望スコアから決定された差（例：20%）以内でスコアを付けた場合のみ合格できます。';
$string['trainingvideo'] = 'トレーニングビデオ';
$string['trainingvideo_help'] = '学生がトレーニング事前テストで練習し、採点を完了するためのビデオをアップロードしてください。';
$string['tryagain'] = '再試行';
$string['typeofassessment'] = '評価の種類';
$string['unassociated'] = '未関連付け';
$string['upload'] = 'アップロード';
$string['uploadedat'] = 'アップロード日時';
$string['uploadedtime'] = 'アップロード時刻';
$string['uploadfile'] = 'ビデオファイルをアップロード';
$string['uploadfile_help'] = '2つの段階があります：ファイルのアップロード、そしてファイルの変換です。変換プロセスはファイルを1/10のサイズに圧縮します。時には長い時間がかかります—10分以上。カメラが4Kに設定されていないか確認してください。これは高すぎるので、解像度とfpsを下げてください。VGAまたは720HD、30fpsが良いです。';
$string['uploadfilename'] = 'ファイル名';
$string['uploadfilesize'] = 'サイズ';
$string['uploadingvideo'] = '動画のアップロード / リンク';
$string['uploadingvideo_help'] = '評価のために録画したパフォーマンスを3つの方法で共有できます。この画面では、学生と教師は以下ができます：
<br />1）ここにビデオパフォーマンスの単一ファイルをアップロードするか
<br />2）ファイルをYouTubeにアップロードしてそのビデオにリンクする。高速応答のためにカメラを最低解像度に設定してください。デバイスで単一のビデオファイルを録画してここにアップロードしてください。さらに、教師は以下ができます：
<br />3）一括アップロード用にSDカードに録画する。そのプロセスには「ビデオ管理」>>「一括アップロード」に移動してください。注意：この画面は「学生のビデオアップロードを許可」のデフォルトが「はい」に保たれている場合のみ学生に利用可能です。';
$string['uploadingvideonotice'] = 'ただいまアップロード中です。しばらくお待ちください';
$string['uploadmessage'] = 'あなたのビデオファイルは500MBを超えています。低い解像度でビデオを撮り直すか、小さいファイルを再アップロードしてください。';
$string['uploadmimetype'] = 'タイプ';
$string['uploadprogress'] = '進行状況';
$string['uploadstatus'] = 'ステータス';
$string['uploadvideo'] = '動画をアップロード / リンク';
$string['uploadvideo_help'] = 'ここで教師はリンクをクリックして単一のビデオファイルをアップロードできます。ファイルには1人の学生のパフォーマンスが含まれている必要があります。各パフォーマンスのビデオは別々に録画してください。ビデオアップロード中、ファイルは元のサイズの10%に圧縮されます。';
$string['uploadvideos'] = 'ビデオをアップロード';
$string['uploadyoutube'] = '外部動画リンクを挿入';
$string['uploadyoutube_help'] = 'より良いパフォーマンスのために、ビデオを個人のYouTubeアカウントまたは他のビデオ共有サイトにアップロードしてください。その後、リンクをコピーしてそのリンクのボックスに貼り付けてください。YouTubeファイルにリンクする場合、評価画面にサムネイル写真は表示されません。ビデオを再生するだけで表示されます。';
$string['url'] = 'URL';
$string['url_error'] = '正しいYouTube URLを入力してください';
$string['url_help'] = 'これはYouTube URLです';
$string['usedpeers'] = 'ピア評価数';
$string['usedpeers_help'] = '教師はピア評価数を0〜3に設定できます。評価メニューで、教師はピアを自動または手動で割り当てることができます。デフォルトは常に「0」ピアですが、ピア評価のパーセンテージが0%を超えて割り当てられた場合は、デフォルトが「1」になり、1〜3で手動で再設定できます。';
$string['video'] = 'ビデオ';
$string['videoalreadyassociated'] = '{$a}は既にビデオに関連付けられています。';
$string['videoassessment:addinstance'] = '新しいビデオアセスメントを追加';
$string['videoassessment:associate'] = '一括アップロードされたビデオをユーザーに関連付け';
$string['videoassessment:bulkupload'] = '一括ビデオアップロード';
$string['videoassessment:bulkupload_help'] = '教師は複数のビデオファイルをこのウィンドウにドラッグできます。ファイルは順番にアップロードされ、変換されます。解像度によっては10〜20ファイルで1時間かかる場合があります。効率的な処理には大きすぎ、パフォーマンス評価の目的には不要なため、4Kなどの高解像度ビデオは避けてください。VGA品質または720HD 30fpsが推奨されます。テストでは、すべてのビデオ形式が互換性がありました。';
$string['videoassessment:exportownsubmission'] = '自分の提出をエクスポート';
$string['videoassessment:fetchcourses'] = 'ビデオ公開用のコース一覧にアクセス';
$string['videoassessment:fetchsections'] = 'ビデオ公開用のコースセクションにアクセス';
$string['videoassessment:grade'] = 'ビデオアセスメントを評価';
$string['videoassessment:gradepeer'] = 'ピアビデオアセスメントを評価';
$string['videoassessment:managesorting'] = '学生の並び順を管理';
$string['videoassessment:submit'] = 'ビデオアセスメントを提出';
$string['videoassessment:view'] = 'ビデオアセスメントを表示';
$string['videoassessment:viewcomments'] = '評価コメントを表示';
$string['videoassessmentname'] = 'ビデオアセスメント名';
$string['videoassessmentnotfound'] = 'ビデオアセスメントが見つかりません。';
$string['videoconverting'] = 'アップロードされた動画は現在バックグラウンドで変換処理中です。処理が完了すると自動的に表示されます。再アップロードの必要はありません。';
$string['videoformat'] = 'ビデオ形式';
$string['videoformatdesc'] = 'ビデオ形式';
$string['videonotfound'] = 'ビデオが見つかりません。';
$string['videonotificationtemplate'] = '[[教師名]]さんへ、
[[学生名]]がビデオファイルをアップロードしました。
それを視聴し、評価するには、以下に移動してください：[[自己評価ページへのリンクを挿入]]
よろしくお願いします、
https://moodle.sgu.ac.jp';
$string['videos'] = 'ビデオ';
$string['videouploadforthefirsttime'] = '学生が初回ビデオアップロード時';
$string['videouploadnotificationlabel'] = 'ビデオアップロード/再アップロード通知';
$string['viewassessmentsofmyvideo'] = '私のビデオの評価を表示';
$string['viewassociatedvideos'] = '関連付けられたビデオを表示';
$string['viewresult'] = '結果を表示';
$string['weighting'] = '重み付け';
$string['whatinfomationtosend'] = '送信する情報';
$string['whatinfomationtosendcontents'] = '<div class="max-with">[[学生名]]<br/>[[VA課題名]]<br/>[[現在の日付]]<br/>[[評価レポート全体を表示するリンク]]->レポートを表示<br/>[[教師メールアドレス]]<br/>[[教師名]]</div>';
$string['whenevervideoupload'] = '学生がビデオを再アップロードするたび';
$string['whentosendnotification'] = 'いつ通知を送信するか';
$string['within'] = '以内';
$string['xfeedback'] = '{$a}フィードバック';
$string['xunassignedstudents'] = '{$a}未割り当て学生';
$string['yes'] = 'はい';
