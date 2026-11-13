<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Blocks\BlockCatalogService;
use Illuminate\Console\Command;

final class DescribeBlocksCommand extends Command
{
    protected $signature = 'cms:blocks:describe {type? : Identificador exacto del bloque} {--entity-type=page} {--entity-id=} {--locale=es} {--market=es} {--format=json : json o markdown}';

    protected $description = 'Consulta campos, valores iniciales y apariencia desde el esquema real de los bloques activos.';

    public function handle(BlockCatalogService $catalog): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['json', 'markdown'], true)) {
            $this->error('Selecciona json o markdown.');

            return self::INVALID;
        }
        $type = $this->argument('type');
        $result = $catalog->describe((string) $this->option('entity-type'), (string) $this->option('locale'), (string) $this->option('market'), $this->option('entity-id') ? (int) $this->option('entity-id') : null, is_string($type) ? $type : null, $type ? 'detail' : 'summary', false);
        if ($format === 'json') {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($result['blocks'] as $definition) {
                $this->line('## '.$definition['name'].' (`'.$definition['type'].'`)');
                $this->line($definition['description']);
                if (isset($definition['schema'])) {
                    $this->line("\n| Campo | Tipo | Descripción |\n| --- | --- | --- |");
                    foreach ($definition['schema'] as $key => $field) {
                        $this->line('| '.$key.' | '.($field['type'] ?? 'text').' | '.str_replace('|', '\\|', (string) ($field['description'] ?? $field['label'] ?? '')).' |');
                    }
                    $this->line("\n```json\n".json_encode($definition['default_payload'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```");
                }
            }
        }

        if ($format === 'markdown' && isset($result['appearance'])) {
            $this->line("\n## Apariencia compartida\n\n| Campo | Tipo |\n| --- | --- |");
            foreach ($result['appearance']['properties'] as $key => $field) {
                $this->line('| '.$key.' | '.$field['type'].' |');
            }
        }

        return self::SUCCESS;
    }
}
