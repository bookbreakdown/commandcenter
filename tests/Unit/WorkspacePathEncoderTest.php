<?php

namespace Tests\Unit;

use App\Services\WorkspacePathEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspacePathEncoderTest extends TestCase
{
    #[DataProvider('paths')]
    public function test_encodes_path_to_claude_on_disk_format(string $input, string $expected): void
    {
        $this->assertSame($expected, (new WorkspacePathEncoder)->encode($input));
    }

    public static function paths(): array
    {
        return [
            'posix-absolute'   => ['/var/www/myproject', '-var-www-myproject'],
            'posix-with-dashes-in-name' => ['/var/www/tmo-tools3', '-var-www-tmo-tools3'],
            'windows-cwd'      => ['C:\\wamp\\www\\tmo-tools3', 'C--wamp-www-tmo-tools3'],
            'windows-uppercase'=> ['D:\\Projects\\App', 'D--Projects-App'],
            'mixed-slashes'    => ['C:/wamp/www/tmo-tools', 'C--wamp-www-tmo-tools'],
        ];
    }
}
