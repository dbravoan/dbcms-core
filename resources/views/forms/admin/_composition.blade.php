<x-admin.form-card class="rounded-xl space-y-4">
<div x-data="formComposition(@js(old('layout', $composition ?? null)), @js(old('actions', $formActions ?? [])), @js(old('fields', $fields)), @js($langs->pluck('code')->all()))"
     x-on:cms-form-fields-changed.window="fieldKeys = $event.detail">
    <h3 class="font-bold">3. Pasos y disposición</h3>
    <p class="text-[color:var(--admin-muted)] my-2">Distribuye los campos en pasos y grupos. Cada campo debe aparecer una vez.</p>
    <label class="flex gap-2 my-3"><input type="checkbox" x-model="enabled"> Personalizar la disposición</label>
    <input type="hidden" name="layout" :value="JSON.stringify(enabled ? layout : null)">
    <div x-show="enabled" class="space-y-4">
        <template x-for="(step, si) in layout.steps" :key="step.key">
            <fieldset class="border p-3 rounded space-y-3">
                <legend x-text="'Paso ' + (si + 1)"></legend>
                @foreach($langs as $lang)
                    <label class="block">Título ({{ $lang->code }})
                        <input class="admin-control" type="text" x-model="step.labels['{{ $lang->code }}']">
                    </label>
                @endforeach
                <template x-for="(group, gi) in step.groups" :key="group.key">
                    <fieldset class="border p-3 rounded space-y-3">
                        <legend x-text="'Grupo ' + (gi + 1)"></legend>
                        @foreach($langs as $lang)
                            <label class="block">Título ({{ $lang->code }})
                                <input class="admin-control" type="text" x-model="group.labels['{{ $lang->code }}']">
                            </label>
                        @endforeach
                        <div class="flex gap-3 flex-wrap">
                            <template x-for="breakpoint in ['mobile','tablet','desktop']" :key="breakpoint">
                                <label><span x-text="({mobile:'Columnas en móvil',tablet:'Columnas en tableta',desktop:'Columnas en escritorio'})[breakpoint]"></span>
                                    <select class="admin-control" x-model.number="group.columns[breakpoint]">
                                        <template x-for="n in (breakpoint === 'mobile' ? [1,2] : [1,2,3,4])" :key="n"><option :value="n" x-text="n"></option></template>
                                    </select>
                                </label>
                            </template>
                        </div>
                        <label class="block">Campos del grupo
                            <select class="admin-control block w-full" multiple x-model="group.fields">
                                <template x-for="key in fieldKeys" :key="key"><option :value="key" x-text="key"></option></template>
                            </select>
                        </label>
                        <button type="button" class="admin-btn admin-btn--neutral" @click="step.groups.splice(gi,1)">Eliminar grupo</button>
                    </fieldset>
                </template>
                <button type="button" class="admin-btn admin-btn--neutral" @click="step.groups.push(group())">Añadir grupo</button>
                <button type="button" class="admin-btn admin-btn--neutral" @click="move(layout.steps,si,-1)" :disabled="si===0">Subir paso</button>
                <button type="button" class="admin-btn admin-btn--neutral" @click="move(layout.steps,si,1)" :disabled="si===layout.steps.length-1">Bajar paso</button>
                <button type="button" class="admin-btn admin-btn--neutral" @click="layout.steps.splice(si,1)">Eliminar paso</button>
            </fieldset>
        </template>
        <button type="button" class="admin-btn admin-btn--neutral" @click="addStep()">Añadir paso</button>
    </div>
    <h3 class="font-bold mt-6">4. Después del envío</h3>
    <p class="text-[color:var(--admin-muted)] my-2">El correo utiliza la dirección de notificaciones. Los webhooks ofrecen solo destinos configurados y revisados.</p>
    <input type="hidden" name="actions" :value="JSON.stringify(actions)">
    <template x-for="(action, ai) in actions" :key="action.id">
        <div class="flex gap-3 my-3 flex-wrap">
            <label>Acción
                <select class="admin-control" x-model="action.type" @change="action.target = ''">
                    <option value="confirm">Mostrar confirmación</option><option value="redirect">Redirigir</option>
                    <option value="email">Enviar correo</option><option value="webhook">Enviar webhook</option>
                    <option value="open_popup">Abrir popup</option><option value="close_popup">Cerrar popup</option>
                </select>
            </label>
            <label x-show="['redirect','open_popup','close_popup'].includes(action.type)">Destino
                <input class="admin-control" type="text" x-model="action.target" :placeholder="action.type === 'redirect' ? '/gracias' : 'identificador-del-popup'">
            </label>
            <label x-show="action.type === 'webhook'">Destino revisado
                <select class="admin-control" x-model="action.target">
                    <option value="">Selecciona un destino</option>
                    @foreach($webhookTargets ?? [] as $target)<option value="{{ $target }}">{{ $target }}</option>@endforeach
                </select>
            </label>
            <button type="button" class="admin-btn admin-btn--neutral" @click="move(actions,ai,-1)" :disabled="ai===0">Subir</button>
            <button type="button" class="admin-btn admin-btn--neutral" @click="actions.splice(ai,1)">Eliminar</button>
        </div>
    </template>
    <button type="button" class="admin-btn admin-btn--neutral" @click="actions.push({id:uid('action'),type:'confirm',target:''})">Añadir acción</button>
</div>
</x-admin.form-card>
<script nonce="{{ $cspNonce ?? '' }}">
function formComposition(layout, actions, fields, locales) {
    if (typeof layout === 'string') { try { layout=JSON.parse(layout); } catch (_) { layout=null; } }
    if (typeof actions === 'string') { try { actions=JSON.parse(actions); } catch (_) { actions=[]; } }
    return {
        enabled: !!layout, layout: layout || {steps:[]}, actions: actions || [],
        fieldKeys:(fields || []).map(field => field.field_key), seq:0,
        uid(prefix) { return prefix + '_' + Date.now().toString(36) + '_' + (++this.seq); },
        labels() { return Object.fromEntries(locales.map(locale => [locale,''])); },
        group() { return {key:this.uid('group'),labels:this.labels(),columns:{mobile:1,tablet:2,desktop:2},fields:[]}; },
        addStep() { const group=this.group(); if (!this.layout.steps.length) group.fields=[...this.fieldKeys]; this.layout.steps.push({key:this.uid('step'),labels:this.labels(),groups:[group]}); },
        move(items,index,delta) { const next=index+delta; if(next<0 || next>=items.length)return; [items[index],items[next]]=[items[next],items[index]]; },
        init() { if (!this.layout.steps.length) this.addStep(); }
    };
}
</script>
