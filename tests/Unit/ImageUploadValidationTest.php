<?php
/**
 * tests/Unit/ImageUploadValidationTest.php
 * ---------------------------------------------------
 * Tests for handle_image_upload() validation logic.
 * Tests the error-checking paths without actually needing
 * real uploaded files (uses mock file arrays).
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class ImageUploadValidationTest extends TestCase
{
    /** @test */
    public function rejects_no_file_uploaded(): void
    {
        $file = ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result); // Error message
    }

    /** @test */
    public function rejects_ini_size_error(): void
    {
        $file = ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result);
        $this->assertStringContainsString('shumë i madh', $result);
    }

    /** @test */
    public function rejects_partial_upload(): void
    {
        $file = ['error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => '', 'size' => 0];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result);
        $this->assertStringContainsString('pjesërisht', $result);
    }

    /** @test */
    public function rejects_oversized_file(): void
    {
        // Create a temp file to pass MIME check
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, 'fake content');

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => 10 * 1024 * 1024, // 10MB
        ];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/', 5 * 1024 * 1024);
        $this->assertIsString($result);
        $this->assertStringContainsString('shumë i madh', $result);

        @unlink($tmp);
    }

    /** @test */
    public function rejects_zero_size_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => 0,
        ];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result);

        @unlink($tmp);
    }

    /** @test */
    public function rejects_non_image_mime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, '<?php echo "hello"; ?>');

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
        ];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result);
        $this->assertStringContainsString('nuk lejohet', $result);

        @unlink($tmp);
    }

    /** @test */
    public function rejects_text_file_disguised_as_image(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, str_repeat('A', 1000));

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => 1000,
        ];
        $result = handle_image_upload($file, sys_get_temp_dir(), '/uploads/');
        $this->assertIsString($result); // Should be rejected

        @unlink($tmp);
    }

    /** @test */
    public function accepts_valid_jpeg_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Create a minimal JPEG in memory
        $img = imagecreatetruecolor(10, 10);
        $tmp = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $destDir = sys_get_temp_dir() . '/phpunit_uploads_' . uniqid();

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
        ];
        $result = handle_image_upload($file, $destDir, '/uploads/');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('mime', $result);
        $this->assertSame('image/webp', $result['mime']);

        // Cleanup
        @unlink($tmp);
        if (isset($result['filename'])) {
            @unlink($destDir . '/' . $result['filename']);
        }
        @rmdir($destDir);
    }

    /** @test */
    public function accepts_valid_png_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreatetruecolor(10, 10);
        $tmp = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        $destDir = sys_get_temp_dir() . '/phpunit_uploads_' . uniqid();

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
        ];
        $result = handle_image_upload($file, $destDir, '/uploads/');

        $this->assertIsArray($result);
        $this->assertSame('image/webp', $result['mime']);

        @unlink($tmp);
        if (isset($result['filename'])) {
            @unlink($destDir . '/' . $result['filename']);
        }
        @rmdir($destDir);
    }

    /** @test */
    public function image_resized_to_max_dimension(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        // Create a 1000x500 image
        $img = imagecreatetruecolor(1000, 500);
        $tmp = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $destDir = sys_get_temp_dir() . '/phpunit_uploads_' . uniqid();

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
        ];
        $result = handle_image_upload($file, $destDir, '/uploads/', 5 * 1024 * 1024, 200);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(200, $result['width']);
        $this->assertLessThanOrEqual(200, $result['height']);

        @unlink($tmp);
        if (isset($result['filename'])) {
            @unlink($destDir . '/' . $result['filename']);
        }
        @rmdir($destDir);
    }

    /** @test */
    public function output_url_contains_public_prefix(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreatetruecolor(10, 10);
        $tmp = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $destDir = sys_get_temp_dir() . '/phpunit_uploads_' . uniqid();

        $file = [
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size'     => filesize($tmp),
        ];
        $result = handle_image_upload($file, $destDir, '/TiranaSolidare/uploads/images/');

        $this->assertIsArray($result);
        $this->assertStringStartsWith('/TiranaSolidare/uploads/images/', $result['url']);

        @unlink($tmp);
        if (isset($result['filename'])) {
            @unlink($destDir . '/' . $result['filename']);
        }
        @rmdir($destDir);
    }
}
