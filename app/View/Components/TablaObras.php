<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TablaObras extends Component
{
    public $obras;
    public $emptyMessage;
    public $generosObra;
    public $idiomasCatalogo;
    public $paisesCatalogo;

    /**
     * Create a new component instance.
     */
    public function __construct($obras, $emptyMessage = null, $generosObra = [], $idiomasCatalogo = [], $paisesCatalogo = [])
    {
        $this->obras = $obras;
        $this->emptyMessage = $emptyMessage;
        $this->generosObra = $generosObra;
        $this->idiomasCatalogo = $idiomasCatalogo;
        $this->paisesCatalogo = $paisesCatalogo;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.tabla-obras');
    }
}