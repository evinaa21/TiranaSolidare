<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/functions.php';

final class HelpRequestTypeNormalizationTest extends TestCase
{
    public function test_explicit_offer_type_is_preserved(): void
    {
        $this->assertSame('offer', ts_help_request_type_value('offer'));
        $this->assertSame('Ofroj ndihmë', ts_help_request_type_label('offer'));
    }

    public function test_blank_type_is_inferred_from_offer_title(): void
    {
        $request = [
            'tipi' => '',
            'titulli' => 'Ofroj tutoriale falas në anglisht',
            'pershkrimi' => 'Jam studente e gjuhës angleze dhe dua të ofroj tutoriale falas.',
        ];

        $this->assertSame('offer', ts_help_request_type_value($request));
        $this->assertSame('Ofroj ndihmë', ts_help_request_type_label($request));
    }

    public function test_blank_type_defaults_to_request_when_offer_signal_is_missing(): void
    {
        $request = [
            'tipi' => '',
            'titulli' => 'Ndihmë me ushqim për familje me 4 anëtarë',
            'pershkrimi' => 'Jemi familje me 4 anëtarë dhe kemi nevojë për ushqime bazë.',
        ];

        $this->assertSame('request', ts_help_request_type_value($request));
        $this->assertSame('Kërkoj ndihmë', ts_help_request_type_label($request));
    }
}