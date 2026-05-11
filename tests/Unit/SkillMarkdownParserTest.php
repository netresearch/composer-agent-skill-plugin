<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\Tests\Unit;

use Netresearch\ComposerAgentSkillPlugin\Util\SkillMarkdownParser;
use PHPUnit\Framework\TestCase;

final class SkillMarkdownParserTest extends TestCase
{
    public function testParsesFrontmatterWithCrlfLineEndings(): void
    {
        $tmp = sys_get_temp_dir() . '/skill-md-crlf-' . bin2hex(random_bytes(4)) . '.md';
        $body = "---\r\nname: crlf-skill\r\ndescription: Uses Windows newlines\r\n---\r\n# Body\r\n";
        file_put_contents($tmp, $body);
        try {
            $parsed = SkillMarkdownParser::parseNameDescription($tmp);
            self::assertNotNull($parsed);
            self::assertSame('crlf-skill', $parsed['name']);
            self::assertSame('Uses Windows newlines', $parsed['description']);
        } finally {
            @unlink($tmp);
        }
    }
}
