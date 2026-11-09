<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Renderer arsitektur modular menjadi representasi SVG multi-pilar.
 */
class ArchitectureDiagramRenderer
{
    public function __construct(
        private readonly MermaidToSvgCompiler $compiler = new MermaidToSvgCompiler
    ) {}

    public function renderModuleTopology(string $moduleName, array $dependencies): string
    {
        $mermaid = "flowchart TD\n";
        $mermaid .= "    MOD[Modul {$moduleName}]\n";

        foreach ($dependencies as $idx => $dep) {
            $mermaid .= "    MOD --> DEP{$idx}[{$dep}]\n";
        }

        return $this->compiler->compile($mermaid);
    }
}
