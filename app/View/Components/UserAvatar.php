<?php

namespace App\View\Components;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserAvatar extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public User $user, public int $size = 40)
    {
        $this->size = min(max($size, 24), 160);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.user-avatar');
    }
}
