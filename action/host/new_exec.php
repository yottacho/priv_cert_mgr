<?php
/****************************************************************************/
/* 호스트 인증서 생성API                                                    */
/****************************************************************************/
// php파일을 직접호출 금지
if ($PROGRAM_NAME == "") error_exit_json("CALL ERROR");

// API 응답
$API_RESPONSE = "Y";

function print_contents()
{
    global $_POST, $_SESSION;
    global $CERT_DATA, $OPENSSL_EXEC;

    if ($_SESSION['user_role'] != "host_manager")
    {
        error_exit_json("host_manager 권한이 필요합니다.");
    }

/*
    $_POST['certificateName']               인증서 이름(IP_호스트명 권장)
    $_POST['countryName']                   국가
    $_POST['organizationName']              회사명
    $_POST['organizationalUnitName']        서버용도 또는 서버명
    $_POST['commonName']                    도메인명
    $_POST['days']                          유효기간

    $_POST['DNS_1']
    $_POST['DNS_2']
    $_POST['DNS_3']
    $_POST['IP_1']
    $_POST['IP_2']
    $_POST['IP_3']
*/

    // --------------------------------------------------------------------- //
    // 루트인증서 체크
    // --------------------------------------------------------------------- //
    if (($rootCaInfo = get_rootca()) === false)
    {
        error_exit_json("루트 인증서가 없습니다.");
    }

    // --------------------------------------------------------------------- //
    // 입력항목 검증
    // --------------------------------------------------------------------- //
    $error_form = array();
    if (!input_value_check($_POST['certificateName'], '^[a-zA-Z0-9-_]*$', 1, 64))
    {
        $error_form['certificateName'] = "알파벳, 숫자, 대시(-), 언더바(_)만 사용가능합니다. 공백, 특수문자는 사용할 수 없습니다.";
    }

    if (!input_value_check($_POST['countryName'], '^[A-Z]*$', 2, 2))
    {
        $error_form['countryName'] = "Use two letter code (ISO 3166-1 alpha-2)";
    }

    if (!input_value_check($_POST['organizationName'], '^[\\x20-\\x7E]*$', 1, 64))
    {
        $error_form['organizationName'] = "Error Organization Name";
    }

    if (!input_value_check($_POST['organizationalUnitName'], '^[\\x20-\\x7E]*$', 1, 64))
    {
        $error_form['organizationalUnitName'] = "Error Organizational Unit Name";
    }

    if (!input_value_check($_POST['commonName'], '^[\\x20-\\x7E]*$', 1, 64))
    {
        $error_form['commonName'] = "Error Common Name";
    }

    if (!input_value_check($_POST['days'], '^[0-9]*$', 1, 5))
    {
        $error_form['days'] = "숫자만 입력가능합니다.";
    }

    // 와일드카드 도메인(*.abc.com) 지원 추가할 필요가 있음
    if (!input_value_check($_POST['DNS_1'], '^[a-z0-9\\.-]+$', 0, 64))
    {
        $error_form['DNS_1'] = "DNS name has alphabet, number, hyphen(-)";
    }

    if (!input_value_check($_POST['DNS_2'], '^[a-z0-9\\.-]+$', 0, 64))
    {
        $error_form['DNS_2'] = "DNS name has alphabet, number, hyphen(-)";
    }

    if (!input_value_check($_POST['DNS_3'], '^[a-z0-9\\.-]+$', 0, 64))
    {
        $error_form['DNS_3'] = "DNS name has alphabet, number, hyphen(-)";
    }

    if (!input_value_check($_POST['IP_1'], '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$', 0, 64))
    {
        $error_form['IP_1'] = "IP format error";
    }

    if (!input_value_check($_POST['IP_2'], '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$', 0, 64))
    {
        $error_form['IP_2'] = "IP format error";
    }

    if (!input_value_check($_POST['IP_3'], '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$', 0, 64))
    {
        $error_form['IP_3'] = "IP format error";
    }

    // --------------------------------------------------------------------- //
    // 기발급 인증서 확인
    // --------------------------------------------------------------------- //
    if (get_cert($_POST['certificateName']) !== false)
    {
        $error_form['certificateName'] = "입력한 인증서 이름으로 인증서가 존재합니다.";
        //error_exit_json("Certificate name already used", null, $error_form);
    }

    if (count($error_form) > 0)
    {
        error_exit_json("입력한 항목에 오류가 있습니다.", null, $error_form);
    }

    // --------------------------------------------------------------------- //
    // 변수 생성
    // --------------------------------------------------------------------- //
    $dir_cert          = $CERT_DATA."/".$_POST['certificateName'];
    $file_openssl_conf = $dir_cert."/".$_POST['certificateName']."_openssl.conf";
    $file_cert_encpw   = $dir_cert."/encpw.txt";
    $file_root_encpw   = $dir_cert."/_encpw2.txt";
    $file_cert_ref     = $dir_cert."/".$_POST['certificateName'].".json";

    // --------------------------------------------------------------------- //
    // 디렉터리 생성
    // --------------------------------------------------------------------- //
    // 다른 오류가 발생해서 생성 도중에 멈춘경우 clear
    clean_cert($_POST['certificateName']);

    if (!is_dir($dir_cert))
    {
        if (!mkdir($dir_cert))
        {
            log_write("Can't create directory", "ERROR");
            error_exit_json("디렉터리를 생성할 수 없습니다.");
        }
    }

    // --------------------------------------------------------------------- //
    // 인증서 발급용 openssl config 생성
    // --------------------------------------------------------------------- //
    $altNames = 'subjectAltName          = @alt_names';
    if ($_POST['DNS_1'] == "" && $_POST['IP_1'] == "")
        $altNames = "";

    $openssl_cert_config = array(
        '[ req ]',
        'default_bits            = 2048',
        'default_md              = sha256',
        'default_keyfile         = '.$CERT_DATA.'/rootca/'.$rootCaInfo['privKeyFile'],
        'distinguished_name      = req_distinguished_name',
        'extensions              = v3_user',
        '',
        '[ v3_user ]',
        'basicConstraints        = CA:FALSE',
        'authorityKeyIdentifier  = keyid, issuer',
        'subjectKeyIdentifier    = hash',
        'keyUsage                = nonRepudiation, digitalSignature, keyEncipherment',
        'extendedKeyUsage        = serverAuth, clientAuth',
        $altNames,
        '',
        '[ alt_names ]',
        ($_POST['DNS_1'] == "" ? "" : 'DNS.1   = '.$_POST['DNS_1']),
        ($_POST['DNS_2'] == "" ? "" : 'DNS.2   = '.$_POST['DNS_2']),
        ($_POST['DNS_3'] == "" ? "" : 'DNS.3   = '.$_POST['DNS_3']),
        ($_POST['IP_1']  == "" ? "" : 'IP.1    = '.$_POST['IP_1']),
        ($_POST['IP_2']  == "" ? "" : 'IP.2    = '.$_POST['IP_2']),
        ($_POST['IP_3']  == "" ? "" : 'IP.3    = '.$_POST['IP_3']),
/*
'DNS.1   = '.$_POST['DNS_1'],
'DNS.2   = '.$_POST['DNS_2'],
'DNS.3   = '.$_POST['DNS_3'],
'IP.1    = '.$_POST['IP_1'],
'IP.2    = '.$_POST['IP_2'],
'IP.3    = '.$_POST['IP_3'],
*/
'',
'[ req_distinguished_name ]',
'countryName                     = Country Name (2 letter code)',
'countryName_default             = '.$_POST['countryName'],
'countryName_min                 = 2',
'countryName_max                 = 2',
'',
'organizationName                = Organization Name (eg, company)',
'organizationName_default        = '.$_POST['organizationName'],
'',
'organizationalUnitName          = Organizational Unit Name (eg, section)',
'organizationalUnitName_default  = '.$_POST['organizationalUnitName'],
'',
'commonName                      = Common Name (eg, your name or your server\'s hostname)',
'commonName_default              = '.$_POST['commonName'],
'commonName_max                  = 64',
''
    );
    file_put_contents($file_openssl_conf, implode("\n", $openssl_cert_config));

    // password file 생성
    file_put_contents($file_cert_encpw, get_ca_master_password());
    file_put_contents($file_root_encpw, get_ca_master_password('rootca_pw'));
    //@unlink($file_cert_encpw);
    //@unlink($file_root_encpw);

    // 참조 파일 생성
    $dt_calc = date_create();
    $dt_calc->add(date_interval_create_from_date_string($_POST['days']." days"));

    $startDate = date("Y/m/d H:i:sO");
    $endDate   = $dt_calc->format("Y/m/d H:i:sO");

    $certmgr_ref = array(
         'certificateName'        => $_POST['certificateName']
        ,'countryName'            => $_POST['countryName']
        ,'organizationName'       => $_POST['organizationName']
        ,'organizationalUnitName' => $_POST['organizationalUnitName']
        ,'commonName'             => $_POST['commonName']
        ,'days'                   => ($_POST['days'] * 1)
        ,'startDateLocal'         => $startDate
        ,'endDateLocal'           => $endDate
        ,'serial'                 => 0
        ,'user'                   => $_SESSION['user_name'].'('.$_SESSION['user_id'].')'
        ,'privKeyFile'            => $_POST['certificateName'].'.key'
        ,'csrFile'                => $_POST['certificateName'].'.csr'
        ,'crtFile'                => $_POST['certificateName'].'.crt'
    );

    // --------------------------------------------------------------------- //
    // Private key 생성 (genrsa)
    // --------------------------------------------------------------------- //
    $genrsa_exec = $OPENSSL_EXEC.' genrsa -aes256 -passout "file:'.$file_cert_encpw.'" '.
        '-out "'.$dir_cert.'/'.$certmgr_ref['privKeyFile'].'" 2048';

    exec($genrsa_exec.' 2>&1', $genrsa_out, $result);
    if ($result != 0)
    {
        @unlink($file_cert_encpw);
        @unlink($file_root_encpw);

        log_write($certmgr_ref['certificateName'].": KEY: ".$genrsa_exec, "ERROR", $genrsa_out);
        error_exit_json("<b>키 생성 오류입니다.</b>\n".
            "<p>".$genrsa_exec."</p>\n<p>".implode("<br>\n", $genrsa_out)."</p>");
    }

    // --------------------------------------------------------------------- //
    // 인증서요청정보 생성(csr)
    // --------------------------------------------------------------------- //
    $csr_req_exec = $OPENSSL_EXEC.' req -batch -new -sha256 -key "'.$dir_cert.'/'.$certmgr_ref['privKeyFile'].'" '.
        '-passin "file:'.$file_cert_encpw.'" -out "'.$dir_cert.'/'.$certmgr_ref['csrFile'].'" '.
        '-config "'.$file_openssl_conf.'"';

    exec($csr_req_exec.' 2>&1', $csr_req_out, $result);
    if ($result != 0)
    {
        @unlink($file_cert_encpw);
        @unlink($file_root_encpw);

        log_write($certmgr_ref['certificateName'].": CSR: ".$csr_req_exec, "ERROR", $csr_req_out);
        error_exit_json("<b>인증요청서(CSR) 생성 오류입니다.</b>\n".
            "<p>".$csr_req_exec."</p>\n<p>".implode("<br>\n", $csr_req_out)."</p>");
    }

    // --------------------------------------------------------------------- //
    // 인증서 생성
    // --------------------------------------------------------------------- //
    $x509_exec = $OPENSSL_EXEC.' x509 -req -days '.$certmgr_ref['days'].' -sha256 -extensions v3_user '.
        '-in "'.$dir_cert.'/'.$certmgr_ref['csrFile'].'" '.
        '-CA "'.$CERT_DATA.'/rootca/'.$rootCaInfo['crtFile'].'" -CAcreateserial '.
        '-CAkey "'.$CERT_DATA.'/rootca/'.$rootCaInfo['privKeyFile'].'" '.
        '-passin "file:'.$file_root_encpw.'" '.
        '-out "'.$dir_cert.'/'.$certmgr_ref['crtFile'].'" '.
        '-extfile "'.$file_openssl_conf.'"';

    exec($x509_exec.' 2>&1', $x509_out, $result);
    if ($result != 0)
    {
        // - 루트인증서 비밀번호 틀리면 오류
        // - rootca 폴더에 웹서버ID가 쓰기권한이 없어도 오류가 발생
        // - 그밖에: SELinux 셋팅 등
        @unlink($file_cert_encpw);
        @unlink($file_root_encpw);

        log_write($certmgr_ref['certificateName'].": x509: ".$x509_exec, "ERROR", $x509_out);
        error_exit_json("<b>인증서 생성 오류입니다. </b>\n".
            "<p>".$x509_exec."</p>\n<p>".implode("<br>\n", $x509_out)."</p>");
    }

    @unlink($file_cert_encpw);
    @unlink($file_root_encpw);

    // 완료되면 참조파일 저장
    file_put_contents($file_cert_ref, json_encode($certmgr_ref, JSON_PRETTY_PRINT));

    log_write($certmgr_ref['certificateName'].": Certificate created. = Success");

    // --------------------------------------------------------------------- //
    // 응답 데이터 생성
    // --------------------------------------------------------------------- //

    $log = "<p>".implode("<br>\n", $genrsa_out)."</p><br>\n";
    $log .= "<p>".implode("<br>\n", $csr_req_out)."</p><br>\n";
    $log .= "<p>".implode("<br>\n", $x509_out)."</p>";

    $output = array();
    $output['certificateName'] = $certmgr_ref['certificateName'];
    $output['privKeyFile']     = $certmgr_ref['privKeyFile'];
    $output['csrFile']         = $certmgr_ref['csrFile'];
    $output['crtFile']         = $certmgr_ref['crtFile'];
    $output['rootCrtFile']     = $rootCaInfo['crtFile'];

    $output['log'] = $log;

    echo json_encode($output);
}
?>
