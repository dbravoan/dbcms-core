<label class="block text-sm">Identificador del tratamiento
    <input @if($dynamic) :name="`company[processing][${index}][owner]`" x-model="row.owner" @else name="company[processing][{{ $index }}][owner]" value="{{ $row['owner'] ?? '' }}" @endif required pattern="[a-z][a-z0-9_\-]*" maxlength="80" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
</label>
<p class="text-xs text-gray-400">Conserva «core» para sesiones y seguridad. Añade únicamente tratamientos que utilices: accounts, blog_comments, forms, shop_inquiries, shop_orders u otro identificador del módulo.</p>
@foreach(['purpose' => '¿Para qué utilizas los datos?', 'legal_basis' => 'Base jurídica', 'data' => '¿Qué datos se tratan?', 'retention' => 'Plazo o criterio de conservación', 'recipients' => 'Destinatarios y proveedores', 'transfers' => 'Transferencias internacionales y garantías, o ausencia de ellas'] as $key => $label)
    <label class="block text-sm">{{ $label }}
        <textarea @if($dynamic) :name="`company[processing][${index}][{{ $key }}]`" x-model="row.{{ $key }}" @else name="company[processing][{{ $index }}][{{ $key }}]" @endif required maxlength="1000" rows="2" class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">@unless($dynamic){{ $row[$key] ?? '' }}@endunless</textarea>
    </label>
@endforeach
<label class="block text-sm">Conservación automática (días)
    <input type="number" min="1" max="36500" @if($dynamic) :name="`company[processing][${index}][retention_days]`" x-model="row.retention_days" :required="['core', 'blog_comments', 'forms', 'shop_inquiries'].includes(row.owner)" @else name="company[processing][{{ $index }}][retention_days]" value="{{ $row['retention_days'] ?? '' }}" @required(in_array($row['owner'] ?? '', ['core', 'blog_comments', 'forms', 'shop_inquiries'], true)) @endif class="block w-full bg-gray-900 border border-gray-600 rounded p-2 mt-1">
</label>
<p class="text-xs text-gray-400">Obligatoria para core, blog_comments, forms y shop_inquiries. Se cuenta desde la recepción; en pedidos, desde la última actividad.</p>
<label class="flex gap-2 text-sm items-start"><input type="checkbox" @if($dynamic) :name="`company[processing][${index}][manual_review]`" x-model="row.manual_review" @else name="company[processing][{{ $index }}][manual_review]" @checked($row['manual_review'] ?? false) @endif value="1" class="mt-1">Revisar el criterio de conservación antes de suprimir datos. Para accounts es obligatorio y no se indican días automáticos.</label>
