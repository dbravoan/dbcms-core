@if(\Illuminate\Support\Facades\Route::has('admin.orchestration.start') || isset($initialExecutionId) || request()->filled('execution'))
<section class="admin-card mb-6" x-data="cmsExecution()">
    @if(\Illuminate\Support\Facades\Route::has('admin.orchestration.start') && ! isset($initialExecutionId))
    <h2 class="text-lg font-semibold">Pídele una tarea al CMS</h2>
    <p class="text-sm text-[color:var(--admin-muted)] mb-3">Usará tus herramientas disponibles. Podrás revisar publicaciones, borrados y envíos antes de aprobarlos.</p>
    <label class="admin-field-label block text-sm" for="cms-instruction">Instrucciones</label>
    <textarea id="cms-instruction" class="admin-control w-full rounded border p-3 dark:bg-[color:var(--admin-surface-raised)]" rows="3" x-model="instruction" placeholder="Crea un borrador sobre…"></textarea>
    <x-admin.button type="button" variant="primary" class="mt-3" x-on:click="start()" x-bind:disabled="busy">Preparar y ejecutar</x-admin.button>
    @else
    <h2 class="text-lg font-semibold">Revisa la acción solicitada</h2>
    @endif
    <p role="status" class="mt-3" x-text="error"></p>
    <template x-if="execution">
        <div class="mt-4 space-y-3">
            <p class="font-medium" x-text="execution.plan?.summary || execution.instruction"></p>
            <p x-text="label(execution.status)"></p>
            <template x-for="step in execution.steps" :key="step.key">
                <div class="rounded border p-3">
                    <p><span x-text="step.result?.action || step.key"></span> · <span x-text="label(step.status)"></span></p>
                    <template x-if="step.status === 'approval_required'">
                        <div>
                            <p class="mt-2">Revisa esta acción y los datos que cambiará:</p>
                            <dl class="space-y-2 mt-2"><template x-for="field in fields({solicitud:step.resolved_arguments, contenido:step.result?.review})" :key="field.name"><div><dt class="text-sm text-[color:var(--admin-muted)]" x-text="field.name"></dt><dd class="whitespace-pre-wrap break-words" x-text="field.value"></dd></div></template></dl>
                            <x-admin.button type="button" variant="warning" class="mt-2" x-on:click="act('approve', {step:step.key,binding_hash:step.binding_hash})">Aprobar este cambio</x-admin.button>
                        </div>
                    </template>
                    <template x-if="step.status === 'completed'"><dl class="space-y-2 mt-2"><template x-for="field in fields(step.result)" :key="field.name"><div><dt class="text-sm text-[color:var(--admin-muted)]" x-text="field.name"></dt><dd class="whitespace-pre-wrap break-words" x-text="field.value"></dd></div></template></dl></template>
                    <p class="text-red-600" x-text="step.error"></p>
                </div>
            </template>
            <template x-if="execution.status === 'needs_input'">
                <div><label for="cms-answer" x-text="execution.question" class="admin-field-label"></label><input id="cms-answer" class="admin-control w-full border rounded p-2" x-model="answer"><button type="button" x-on:click="act('respond', {answer})">Responder</button></div>
            </template>
            <p class="text-red-600" x-text="execution.error"></p>
            <button type="button" x-show="execution.status === 'failed'" x-on:click="act('resume')">Reintentar el paso fallido</button>
            <button type="button" x-show="execution.status === 'approval_required'" x-on:click="act('resume')">Actualizar revisión</button>
            <button type="button" x-show="!['completed','cancelled'].includes(execution.status)" x-on:click="act('cancel')">Cancelar</button>
        </div>
    </template>
</section>
@push('admin_scripts')
<script nonce="{{ $cspNonce ?? '' }}">
function cmsExecution() {
    return {
        instruction:'', answer:'', execution:null, error:'', busy:false, timer:null,
        fields(value, prefix='') { const labels={type:'Tipo',entity_type:'Tipo',id:'Identificador',title:'Título',name:'Nombre',content:'Contenido',summary:'Resumen',locale:'Idioma',status:'Estado',market_ids:'Mercados',market_id:'Mercado',markets:'Mercados',translations:'Traducciones',publish:'Publicar',is_published:'Publicado',is_active:'Visible',price:'Precio',custom_price:'Precio en el mercado',stock_quantity:'Existencias',sku:'Referencia',attributes:'Datos',related_content:'Contenido asociado',items:'Imágenes',image_url:'Imagen',alt_text:'Texto alternativo',caption:'Pie de foto',sections:'Bloques',sections_json:'Bloques',url:'Enlace',provider:'Proveedor',model:'Modelo',revision_id:'Revisión'}; return Object.entries(value||{}).flatMap(([key,item])=>{ if (item===null || item===undefined || /password|secret|api_key|token|binding_hash|_live_hash/.test(key)) return []; const name=[prefix,labels[key]||key.replaceAll('_',' ')].filter(Boolean).join(' · '); return typeof item==='object'?this.fields(item,name):[{name,value:typeof item==='boolean'?(item?'Sí':'No'):String(item)}]; }); },
        init() { const id = @json($initialExecutionId ?? null) || new URLSearchParams(location.search).get('execution'); if (id) { this.execution={id}; this.poll(); } },
        label(status) { return ({planning:'Preparando plan',pending:'Pendiente',running:'En curso',completed:'Completado',needs_input:'Necesita información',approval_required:'Esperando tu aprobación',failed:'Error recuperable',uncertain:'Operación iniciada; resultado pendiente de comprobar',pending_remote:'Esperando al proveedor',cancelled:'Cancelado'})[status] || status; },
        async call(path, data, method='POST') { const response = await fetch(@json(isset($initialExecutionId) ? url(config('cms.admin_path').'/executions') : route('admin.orchestration.start'))+path, {method,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(method==='GET'?{}:{body:JSON.stringify(data||{})})}); const result=await response.json(); if (!response.ok) throw new Error(result.message||'No se pudo completar la solicitud.'); return result; },
        async start() { this.busy=true; this.error=''; try { this.execution=await this.call('',{instruction:this.instruction,idempotency_key:crypto.randomUUID()}); this.poll(); } catch(e) { this.error=e.message; } finally { this.busy=false; } },
        async act(action, data={}) { this.error=''; try { this.execution=await this.call('/'+this.execution.id+'/'+action,data); this.poll(); } catch(e) { this.error=e.message; } },
        async poll() { clearTimeout(this.timer); try { this.execution=await this.call('/'+this.execution.id+(@json(isset($initialExecutionId))?'/status':''),{},'GET'); if (['planning','pending','running','pending_remote'].includes(this.execution.status)) this.timer=setTimeout(()=>this.poll(),2500); } catch(e) { this.error=e.message; } },
        destroy() { clearTimeout(this.timer); }
    };
}
</script>
@endpush
@endif
