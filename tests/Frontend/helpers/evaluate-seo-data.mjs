import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

// Consume the production attribute after Blade rendering and DOM decoding.
// A PHP-only render cannot detect uncompiled directives or invalid Alpine JS.
const { expression, title, description } = JSON.parse(readFileSync(0, 'utf8'));
const data = runInNewContext(`(${expression})`, {}, { timeout: 1000 });
data.activeMetaTitle = title;
data.activeMetaDesc = description;
process.stdout.write(JSON.stringify({
    locale: data.locale,
    languages: data.languages,
    title: data.activeMetaTitle,
    description: data.activeMetaDesc,
    titleLength: data.titleLength,
    descriptionLength: data.descLength,
    previewTitle: data.previewTitle,
    previewDescription: data.previewDesc,
    schema: data.activeSchemaType,
    score: data.score,
}));
