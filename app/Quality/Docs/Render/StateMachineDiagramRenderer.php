<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Renderer state machine transisi status menjadi representasi SVG.
 */
class StateMachineDiagramRenderer
{
    public function __construct(
        private readonly MermaidToSvgCompiler $compiler = new MermaidToSvgCompiler()
    ) {
    }

    /**
     * @param array<string, list<string>> $transitions
     */
    public function renderTransitions(string $smName, array $transitions): string
    {
        $mermaid = "flowchart TD\n";

        foreach ($transitions as $from => $toStates) {
            foreach ($toStates as $to) {
                $mermaid .= "    {$from}[{$from}] --> {$to}[{$to}]\n";
            }
        }

        return $this->compiler->compile($mermaid);
    }
}

