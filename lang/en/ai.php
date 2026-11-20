<?php

declare(strict_types=1);

return ['editor_text_title' => 'Text assistant',
    'image_title' => 'Generate image', 'video_title' => 'Generate video',
    'image_help' => 'Describe the image you need and review the result before assigning it to this field.',
    'video_help' => 'Describe the video you need and select a format and duration supported by your provider.',
    'translation' => [
        'title' => 'Translate with AI',
        'help' => 'Translate this item and its blocks while preserving images, links and structure. Languages follow its assigned markets.',
        'source' => 'Source language', 'target' => 'Target language', 'choose' => 'Select a language',
        'generate' => 'Generate translation', 'working' => 'Preparing translation…', 'apply' => 'Apply translation',
        'preview' => 'Review the proposal before applying it. Save the form afterwards to keep your changes.',
        'replace' => 'I have reviewed the proposal and want to replace the existing text in the target language.',
        'slug' => 'The target language address is preserved. If it is empty, complete it before saving.',
        'stale' => 'Content or markets have changed. Generate a new translation to include those changes.',
        'invalid' => 'Select two different languages from this item’s assigned markets.',
        'empty' => 'Complete the source content and assign at least one market before translating.',
        'failed' => 'Translation could not be generated. Check the connection and your permissions.',
        'timeout' => 'Translation is still running in the background. Check the task before generating again.',
        'protected' => 'These blocks remain untranslated because their module does not declare translatable fields:',
    ]];
