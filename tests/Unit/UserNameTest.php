<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNameTest extends TestCase
{
    #[Test]
    public function it_formats_the_full_name_with_optional_prefix_middle_name_and_suffix(): void
    {
        $user = new User([
            'prefix' => 'Dr.',
            'first_name' => '  Alex  ',
            'middle_name' => '  Miguel   Ángel ',
            'last_name' => 'Morgan',
            'suffix' => 'Jr.',
        ]);

        $this->assertSame('Dr. Alex Miguel Ángel Morgan Jr.', $user->full_name);
    }

    #[Test]
    public function it_formats_a_preferred_display_name_with_the_middle_initial(): void
    {
        $user = new User([
            'first_name' => 'Alex',
            'middle_name' => 'Miguel Ángel',
            'last_name' => 'Morgan',
        ]);

        $this->assertSame('Alex M. Morgan', $user->display_name);
        $this->assertSame('M.', $user->middle_initial);
    }

    #[Test]
    public function it_omits_a_blank_middle_name_and_initial(): void
    {
        $user = new User([
            'first_name' => 'Alex',
            'middle_name' => '   ',
            'last_name' => 'Morgan',
        ]);

        $this->assertSame('Alex Morgan', $user->full_name);
        $this->assertSame('Alex Morgan', $user->display_name);
        $this->assertNull($user->middle_initial);
    }
}
