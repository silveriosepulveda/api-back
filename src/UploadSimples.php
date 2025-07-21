<?php

namespace ClasseGeral;

if (!class_exists('Imagick')) {
    throw new \Exception('Imagick extension is not installed or enabled.');
}


/**
 * Classe utilitária para upload simples de arquivos e imagens.
 */
class UploadSimples
{
    /**
     * Largura máxima da imagem (opcional).
     * @var int
     */
    public $largura;
    /**
     * Altura máxima da imagem (opcional).
     * @var int
     */
    public $altura;
    /**
     * Diretório de destino do upload.
     * @var string
     */
    public $destino;

    /**
     * Realiza o upload de um arquivo ou imagem para o destino especificado.
     *
     * @param array $arquivo Array do arquivo ($_FILES[])
     * @param string $novo_nome Nome do arquivo após upload
     * @param string $destino Caminho de destino
     * @param int $largura Largura máxima da imagem (opcional)
     * @param int $altura Altura máxima da imagem (opcional)
     * @return bool Sucesso ou falha do upload
     */
    public function upload($arquivo, $novo_nome, $destino, $largura = 0, $altura = 0)
    {

        //ini_set('display_errors', 1);
        $extensoes_imagem = array('jpg', 'jpeg', 'gif', 'png');
        $extensoes_arquivos = array('doc', 'docx', 'pdf', 'xls', 'xlsx', 'csv');

        $ext = strtolower(pathinfo($arquivo["name"], PATHINFO_EXTENSION));

        $local = $destino . $novo_nome;

        if (in_array($ext, $extensoes_imagem)) {
            $up = $this->uploadImagem($arquivo, $local, $largura, $altura);
        } else if (in_array($ext, $extensoes_arquivos)) {
            $up = $this->uploadFile($arquivo, $local);
        }

        if ($up == 1) {
            return true;
        } else {
            return false;
        }
        //*/
    }

    /**
     * Realiza o upload de uma imagem, redimensionando se necessário.
     * @param array $file Array do arquivo
     * @param string $path Caminho de destino
     * @param int $maxlar Largura máxima
     * @param int $maxalt Altura máxima
     * @param int $maxsize Tamanho máximo em bytes
     * @return bool Sucesso ou falha
     */
    private function uploadImagem($file, $path, $maxlar = 0, $maxalt = 0, $maxsize = 50072000)
    {
        $file_path = $file['tmp_name'];

        list($img_width, $img_height) = @getimagesize($file_path);

        $img_width = $img_width <= 0 ? 1 : $img_width;
        $img_height = $img_height <= 0 ? 1 : $img_height;

        $scale = min($maxlar / $img_width, $maxalt / $img_height);
        $scale = $scale == 0 ? 1 : $scale;

        if ($scale >= 1) {
            return copy($file_path, $path);
            return true;
        }

        $new_width = intval($img_width * $scale);
        $new_height = intval($img_height * $scale);

        $new_img = @imagecreatetruecolor($new_width, $new_height);

        switch (strtolower(substr(strrchr($path, '.'), 1))) {
            case 'jpg':
            case 'jpeg':
                $src_img = imagecreatefromjpeg($file_path);
                $write_image = 'imagejpeg';
                $image_quality = 80;
                break;
            case 'gif':
                @imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
                $src_img = @imagecreatefromgif($file_path);
                $write_image = 'imagegif';
                $image_quality = null;
                break;
            case 'png':
                @imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
                @imagealphablending($new_img, false);
                @imagesavealpha($new_img, true);
                $src_img = @imagecreatefrompng($file_path);
                $write_image = 'imagepng';
                $image_quality = 9;
                break;
            default:
                $src_img = null;
        }

        $success = $src_img && imagecopyresampled(
                $new_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height
            ) && $write_image($new_img, $path, $image_quality);


        // Free up memory (imagedestroy does not delete files):
        @imagedestroy($src_img);
        @imagedestroy($new_img);

        return $success;
//*/
    }

    private function uploadFile($arquivo, $destino)
    {
        move_uploaded_file($arquivo['tmp_name'], $destino);
    }

    public function mudacor($imagem, $destino, $cor)
    {
        switch (strtolower(substr(strrchr($imagem, '.'), 1))) {
            case 'jpg':
            case 'jpeg':
                $im = imagecreatefromjpeg("$imagem");
                break;
            case 'png':
                $im = imagecreatefrompng("$imagem");
                break;
            case 'gif':
                $im = imagecreatefromgif("$imagem");
                break;
            default:
                $im = null;
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $x = 0;
        $y = 0;
        $lar = imagesx($im);
        $alt = imagesy($im);
        for ($x = 0; $x < $lar; $x++) {
            for ($y = 0; $y < $alt; $y++) {
                $rgb = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
                imagesetpixel($im, $x, $y, $transparent);
                $corHex = $this->HexParaRGB($cor);
                $pixelColor = imagecolorallocatealpha($im, $corHex['r'], $corHex['g'], $corHex['b'], $rgb['alpha']);
                imagesetpixel($im, $x, $y, $pixelColor);
            }
        }
        switch (strtolower(substr(strrchr($imagem, '.'), 1))) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($im, $destino);
                break;
            case 'png':
                imagepng($im, $destino);
                break;
            case 'gif':
                imagegif($im, $destino);
                break;
            default:
                $im = null;
        }
        imagedestroy($im);
    }

    public function HexParaRGB($hex)
    {
        $hex = preg_replace("/#/", "", $hex);
        $cor = array();

        if (strlen($hex) == 3) {
            $cor['r'] = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $cor['g'] = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $cor['b'] = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } elseif (strlen($hex) == 6) {
            $cor['r'] = hexdec(substr($hex, 0, 2));
            $cor['g'] = hexdec(substr($hex, 2, 2));
            $cor['b'] = hexdec(substr($hex, 4, 2));
        }
        return $cor;
    }

    public function criaimagem($largura, $altura, $destino, $cor)
    {
        $im = @imagecreate($largura, $altura);
        $corHex = $this->HexParaRGB($cor);
        $background_color = imagecolorallocate($im, $corHex['r'], $corHex['g'], $corHex['b']);
        imagepng($im, $destino);
        imagedestroy($im);
    }

    public function aplicatransparencia($img, $valor_transp, $destino)
    {
        // $srcImagick = new Imagick($img);
        // $srcImagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        // $srcImagick->evaluateImage(Imagick::EVALUATE_DIVIDE, "$valor_transp", Imagick::CHANNEL_ALPHA);
        // $srcImagick->writeImage($destino);
    }

}

?>
