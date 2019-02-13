<?php
/****************************************************************************/
/* 초기화면                                                                 */
/****************************************************************************/
// php파일을 직접호출 금지
if ($PROGRAM_NAME == "") die("ERROR");

$PAGE_TITLE = "홈";
$PAGE_DESC = "인증서 관리 시작";

function print_contents()
{
?>
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">SSL 인증서 관리시스템</h3>
        </div>
        <div class="box-body">
          사설망 시스템을 위한 SSL(https) 인증서를 관리합니다.
        </div>
      </div>

    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">인증서 마스터 패스워드 조회</h3>
        </div>
        <div class="box-body">
            <div class="box box-solid">
                <button id="id_show_password" class="btn btn-block btn-default btn-sm">조회</button>
            </div>

            <div id="show_master_pw" class="list-group">
                <div class="list-group-item col-sm-4">마스터 패스워드</div>
                <div id="master_pw" class="list-group-item col-sm-8">&nbsp;</div>
            </div>

            <div id="show_rootca_pw" class="list-group">
                <div class="list-group-item col-sm-4">루트인증서 패스워드</div>
                <div id="rootca_pw" class="list-group-item col-sm-8">&nbsp;</div>
            </div>

        </div>
    </div>

<!--
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">메뉴 설명</h3>
        </div>
        <div class="box-body">
          <p>인증서는 루트 인증서와 호스트(서버)인증서가 있습니다.</p>

          <ul>
            <li>Host Certificate - 호스트(서버) 인증서 관리
              <p>호스트 인증서는 각각의 서버에 발급하여 서버의 IP와 도메인을 인증합니다.</p>
              <ul>
                <li>List Host Certificate - 호스트 인증서 목록을 조회합니다.
                  <p>상세보기 또는 인증서 삭제가 가능합니다.</p>
                </li>
                <li>New Host Certificate - 호스트 인증서를 발급합니다.</li>
              </ul>
            </li>

            <li>Root Certificate - 최상위 인증서 관리
              <p>루트 인증서는 호스트(서버)인증서를 인증합니다.<br/>
                하나의 루트 인증서로 다수의 호스트 인증서를 생성할 수 있으므로 루트 인증서는 하나만 발급할 수 있습니다.<br/>
                루트 인증서가 변경되면 클라이언트에 루트 인증서를 재배포해야 하며, 연관된 호스트 인증서가 모두 무효화됩니다.</p>
              <ul>
                <li>View Root Certificate - 루트 인증서를 조회합니다.</li>
                <li>New Root Certificate - 루트 인증서를 발급합니다. (루트 인증서가 이미 발급되어 있으면 발급되지 않습니다.)</li>
                <li>Close Root Certificate - 루트 인증서를 폐기합니다. 호스트 인증서도 함께 폐기됩니다.</li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
-->
<?php
}

function footer_scripts()
{
    global $BASE_URL;
?>
<script src="certmgr_common.js"></script>
<script>
    $(document).ready(function() {
        // click on button submit
        $("#id_show_password").on('click', function () {
            ajax_send('form',
                '<?=$BASE_URL?>?a=cert_password',
                function (result) {
                    //alert('success function ' + result);
                    //console.log(result);

                    $("#master_pw").empty().append(result.master_password);
                    $("#rootca_pw").empty().append(result.rootca_password);

                    // 인증서 링크를 활성화하고 다운로드한다.
                    //$("#id_view_cert").removeClass("hide");
                    //$("#cert_result_log").removeClass("hide");

                },
                function (err) {
                    // json object only
                    if (err == null)
                        return;

                    //alert('error function');
                    //console.log(err);
                }
            );
        })
    });
</script>

<?php
}
?>