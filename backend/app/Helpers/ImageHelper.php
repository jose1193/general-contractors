<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class ImageHelper
{
    public static function storeAndResize($image, $storagePath)
    {
        // Redimensionar la imagen y guardarla temporalmente en local
        $resizedImagePath = self::resizeAndStoreTempImage($image);

        // Generar un nombre de archivo único
        $uniqueFileName = self::generateUniqueFileName();

        // Guardar la imagen redimensionada en S3 con el nombre único
        $photoPath = self::storeImageToS3($resizedImagePath, $storagePath, $uniqueFileName);

        // Eliminar la imagen temporal redimensionada
        unlink($resizedImagePath);

        return $photoPath;
    }

    private static function resizeAndStoreTempImage($image)
    {
        // Crear una imagen de Intervention a partir del archivo
        $image = Image::make($image);

        // Obtener las dimensiones originales
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Redimensionar si es necesario
        if ($originalWidth > 700 || $originalHeight > 700) {
            $scaleFactor = min(700 / $originalWidth, 700 / $originalHeight);
            $newWidth = $originalWidth * $scaleFactor;
            $newHeight = $originalHeight * $scaleFactor;
            $image->resize($newWidth, $newHeight);
        }

        // Guardar la imagen redimensionada temporalmente
        $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.jpg';
        $image->save($tempPath);

        return $tempPath;
    }

    private static function generateUniqueFileName()
    {
        // Generar una cadena única de 40 caracteres
        return Str::random(40);
    }

    private static function storeImageToS3($imagePath, $storagePath, $fileName)
    {
        // Leer la imagen redimensionada desde el almacenamiento temporal
        $resizedImageContent = file_get_contents($imagePath);

        // Definir la ruta de destino en S3
        $s3Path = $storagePath . '/' . $fileName . '.jpg';

        // Subir la imagen redimensionada a S3
        Storage::disk('s3')->put($s3Path, $resizedImageContent);

        // Retornar la URL de la imagen en S3
        return Storage::disk('s3')->url($s3Path);
    }


    //DELETE IMAGE FROM STORAGE
public static function deleteFileFromStorage($fullUrl)
{
    // Extraer la ruta relativa de la URL completa
    $parsedUrl = parse_url($fullUrl);
    $relativePath = ltrim($parsedUrl['path'], '/');

    // Eliminar el nombre del bucket si está presente en la ruta
    $bucketName = env('AWS_BUCKET');
    $relativePath = preg_replace("/^{$bucketName}\//", '', $relativePath);

    try {
        if (Storage::disk('s3')->exists($relativePath)) {
            $deleted = Storage::disk('s3')->delete($relativePath);
           
            return $deleted;
        } else {
            \Log::warning("El archivo no existe en S3: {$relativePath}");
            return false;
        }
    } catch (\Exception $e) {
        \Log::error("Error al eliminar archivo de S3: {$relativePath}. Error: " . $e->getMessage());
        return false;
    }
}





     // Stores and resizes a profile picture, returns the relative path of the stored file, handles exceptions.
    public static function storeAndResizeProfilePhoto($image, $storagePath)
{
    try {
        $photoPath = self::storeImage($image, $storagePath);
        self::resizeImage(storage_path('app/'.$photoPath));
        return 'app/'.$photoPath;
    } catch (\Exception $e) {
        Log::error('Failed to store or resize image: ' . $e->getMessage());
        return null;  // Retorna null o maneja el error como sea apropiado.
    }
}





public static function storeFile($file, $storagePath)
{
    // Generar un nombre de archivo único con la extensión original
    $uniqueFileName = self::generateUniqueFileName() . '.' . $file->getClientOriginalExtension();

    // Definir la ruta de destino en S3
    $s3Path = $storagePath . '/' . $uniqueFileName;

    // Subir el archivo a S3 usando el método adecuado para manejar archivos subidos
    Storage::disk('s3')->put($s3Path, fopen($file->getRealPath(), 'r+'));

    // Retornar la URL del archivo en S3
    return Storage::disk('s3')->url($s3Path);
}

public static function storeSignatureInS3($signatureData, $storagePath)
{
    // Decodificar la firma en base64
    $imageData = base64_decode($signatureData);

    // Crear una imagen a partir de los datos decodificados
    $image = Image::make($imageData);

    // Redimensionar la imagen y guardarla temporalmente
    $resizedImagePath = self::resizeAndStoreTempImage($image);

    // Generar un nombre de archivo único
    $uniqueFileName = self::generateUniqueFileName() . '.png';

    // Definir la ruta de destino en S3
    $s3Path = $storagePath . '/' . $uniqueFileName;

    // Subir la imagen redimensionada a S3
    Storage::disk('s3')->put($s3Path, file_get_contents($resizedImagePath));

    // Eliminar la imagen temporal redimensionada
    unlink($resizedImagePath);

    // Retornar la URL del archivo en S3
    return Storage::disk('s3')->url($s3Path);
}



public static function storePDFAgreement($pdfContent, $storagePath)
{
    // Definir la ruta de destino en S3
    $s3Path = $storagePath;

    // Subir el archivo a S3 directamente desde el contenido del PDF
    Storage::disk('s3')->put($s3Path, $pdfContent);

    // Retornar la URL del archivo en S3
    return Storage::disk('s3')->url($s3Path);
}


//public static function storePDFAgreement($pdf, $storagePath)
//{
    // Definir la ruta de destino en S3
    //$s3Path = $storagePath;

    // Subir el archivo a S3 directamente desde el contenido del PDF
    //Storage::disk('s3')->put($s3Path, $pdf->output());

    // Retornar la URL del archivo en S3
    //return Storage::disk('s3')->url($s3Path);
//}



}
