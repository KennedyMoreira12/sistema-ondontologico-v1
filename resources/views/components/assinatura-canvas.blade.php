@props([
    'action',
    'modalId',
    'title' => 'Assinatura do paciente',
    'documentTitle' => '',
    'patientName' => '',
    'buttonLabel' => 'Assinar documento',
    'buttonClass' => 'btn btn-primary',
    'icon' => 'bi-pen',
    'confirmLabel' => 'Confirmar assinatura',
])

{{--
    Área de assinatura manuscrita.

    O mesmo componente serve para o termo de responsabilidade e para a
    confirmação de presença de uma sessão. O desenho é feito em um canvas com
    `touch-action: none`: o dedo, a caneta touch e o mouse usam Pointer Events,
    e o gesto não rola a página por acidente.

    Os pontos são guardados normalizados (0..1), então girar o tablet ou mudar
    o tamanho da janela não perde o traço.
--}}

<button type="button" class="{{ $buttonClass }}" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
    <i class="bi {{ $icon }} me-1"></i>{{ $buttonLabel }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true"
     data-assinatura-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-assinatura-form>
                @csrf
                <input type="hidden" name="assinatura" data-assinatura-input>

                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 mb-0" id="{{ $modalId }}-titulo">{{ $title }}</h2>
                        @if ($documentTitle !== '')
                            <p class="text-muted small mb-0">{{ $documentTitle }}</p>
                        @endif
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    @if ($patientName !== '')
                        <p class="mb-2">
                            <span class="text-muted">Paciente:</span>
                            <strong>{{ $patientName }}</strong>
                        </p>
                    @endif

                    <p class="text-muted small mb-2">
                        Assine no espaço branco abaixo usando o dedo, uma caneta touch ou o mouse.
                        No tablet, você pode assinar na horizontal ou na vertical.
                    </p>

                    <div class="position-relative rounded border border-2 bg-white overflow-hidden"
                         style="border-style: dashed !important; border-color: #adb5bd;">
                        <canvas
                            data-assinatura-canvas
                            aria-label="Área de assinatura manuscrita"
                            style="display: block; width: 100%; height: clamp(240px, 45vh, 460px); touch-action: none; cursor: crosshair;"
                        >Seu navegador não suporta a área de assinatura.</canvas>
                        <span data-assinatura-hint
                              class="position-absolute top-50 start-50 translate-middle text-muted user-select-none"
                              style="pointer-events: none; font-size: 1.05rem;">
                            Assine aqui
                        </span>
                    </div>

                    <div class="alert alert-warning small mt-3 mb-0" role="note">
                        <i class="bi bi-shield-check me-1"></i>
                        Assinatura manuscrita eletrônica com registro de evidências e integridade.
                        <strong>Não é assinatura digital qualificada ICP-Brasil.</strong>
                    </div>

                    <p class="text-danger small mt-2 mb-0" data-assinatura-error hidden></p>
                </div>

                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-assinatura-limpar>
                        <i class="bi bi-eraser me-1"></i>Limpar
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-assinatura-refazer
                            title="Desfaz o último traço; com a área vazia, restaura o traço desfeito">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Refazer
                    </button>
                    <button type="submit" class="btn btn-success btn-lg" data-assinatura-confirmar disabled>
                        <i class="bi bi-check2-circle me-1"></i>{{ $confirmLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                if (window.__assinaturaCanvasReady) {
                    return;
                }

                window.__assinaturaCanvasReady = true;

                function preparar(modal) {
                    var canvas = modal.querySelector('[data-assinatura-canvas]');
                    var form = modal.querySelector('[data-assinatura-form]');
                    var input = modal.querySelector('[data-assinatura-input]');
                    var confirmar = modal.querySelector('[data-assinatura-confirmar]');
                    var limpar = modal.querySelector('[data-assinatura-limpar]');
                    var refazer = modal.querySelector('[data-assinatura-refazer]');
                    var hint = modal.querySelector('[data-assinatura-hint]');
                    var erro = modal.querySelector('[data-assinatura-error]');

                    if (!canvas) {
                        return;
                    }

                    var ctx = canvas.getContext('2d');
                    var tracos = [];
                    var desfeitos = [];
                    var atual = null;
                    var tamanho = { w: 1, h: 1 };

                    function medir() {
                        var rect = canvas.getBoundingClientRect();
                        var dpr = window.devicePixelRatio || 1;

                        tamanho.w = Math.max(1, rect.width);
                        tamanho.h = Math.max(1, rect.height);

                        canvas.width = Math.round(tamanho.w * dpr);
                        canvas.height = Math.round(tamanho.h * dpr);

                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                        ctx.lineWidth = 2.6;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.strokeStyle = '#0b1f2a';

                        desenhar();
                    }

                    function ponto(evento) {
                        var rect = canvas.getBoundingClientRect();

                        return {
                            x: (evento.clientX - rect.left) / Math.max(1, rect.width),
                            y: (evento.clientY - rect.top) / Math.max(1, rect.height),
                        };
                    }

                    function desenhar() {
                        ctx.clearRect(0, 0, tamanho.w, tamanho.h);

                        tracos.forEach(function (traco) {
                            if (!traco.length) {
                                return;
                            }

                            ctx.beginPath();

                            if (traco.length === 1) {
                                ctx.arc(traco[0].x * tamanho.w, traco[0].y * tamanho.h, 1.4, 0, Math.PI * 2);
                                ctx.fillStyle = '#0b1f2a';
                                ctx.fill();

                                return;
                            }

                            ctx.moveTo(traco[0].x * tamanho.w, traco[0].y * tamanho.h);

                            for (var i = 1; i < traco.length; i++) {
                                var anterior = traco[i - 1];
                                var proximo = traco[i];
                                var meioX = ((anterior.x + proximo.x) / 2) * tamanho.w;
                                var meioY = ((anterior.y + proximo.y) / 2) * tamanho.h;

                                ctx.quadraticCurveTo(anterior.x * tamanho.w, anterior.y * tamanho.h, meioX, meioY);
                            }

                            var ultimo = traco[traco.length - 1];
                            ctx.lineTo(ultimo.x * tamanho.w, ultimo.y * tamanho.h);
                            ctx.stroke();
                        });
                    }

                    function atualizarEstado() {
                        var temTinta = tracos.length > 0;

                        if (hint) {
                            hint.style.opacity = temTinta ? '0' : '1';
                        }

                        if (confirmar) {
                            confirmar.disabled = !temTinta;
                        }

                        if (refazer) {
                            refazer.disabled = tracos.length === 0 && desfeitos.length === 0;
                        }
                    }

                    function limparTudo() {
                        tracos = [];
                        desfeitos = [];
                        atual = null;

                        desenhar();
                        atualizarEstado();

                        if (erro) {
                            erro.hidden = true;
                            erro.textContent = '';
                        }
                    }

                    function refazerUltimo() {
                        if (tracos.length > 0) {
                            desfeitos.push(tracos.pop());
                        } else if (desfeitos.length > 0) {
                            tracos.push(desfeitos.pop());
                        }

                        desenhar();
                        atualizarEstado();
                    }

                    function iniciar(evento) {
                        if (evento.pointerType === 'mouse' && evento.button !== 0) {
                            return;
                        }

                        evento.preventDefault();

                        if (canvas.setPointerCapture) {
                            try {
                                canvas.setPointerCapture(evento.pointerId);
                            } catch (e) {
                                // O navegador pode recusar o capture; desenhar continua.
                            }
                        }

                        atual = [ponto(evento)];
                        tracos.push(atual);

                        desenhar();
                        atualizarEstado();
                    }

                    function mover(evento) {
                        if (!atual) {
                            return;
                        }

                        evento.preventDefault();
                        atual.push(ponto(evento));
                        desenhar();
                    }

                    function terminar(evento) {
                        if (!atual) {
                            return;
                        }

                        if (evento && evento.preventDefault) {
                            evento.preventDefault();
                        }

                        atual = null;
                        desfeitos = [];

                        desenhar();
                        atualizarEstado();
                    }

                    canvas.addEventListener('pointerdown', iniciar);
                    canvas.addEventListener('pointermove', mover);
                    canvas.addEventListener('pointerup', terminar);
                    canvas.addEventListener('pointercancel', terminar);
                    canvas.addEventListener('pointerleave', function (evento) {
                        if (atual) {
                            terminar(evento);
                        }
                    });

                    if (limpar) {
                        limpar.addEventListener('click', limparTudo);
                    }

                    if (refazer) {
                        refazer.addEventListener('click', refazerUltimo);
                    }

                    if (form) {
                        form.addEventListener('submit', function (evento) {
                            if (tracos.length === 0) {
                                evento.preventDefault();

                                if (erro) {
                                    erro.hidden = false;
                                    erro.textContent = 'Assine na área branca antes de confirmar.';
                                }

                                return;
                            }

                            if (input) {
                                input.value = canvas.toDataURL('image/png');
                            }
                        });
                    }

                    modal.addEventListener('shown.bs.modal', function () {
                        medir();
                        atualizarEstado();
                    });

                    window.addEventListener('resize', function () {
                        if (modal.classList.contains('show')) {
                            medir();
                        }
                    });

                    atualizarEstado();
                }

                document.querySelectorAll('[data-assinatura-modal]').forEach(preparar);
            })();
        </script>
    @endpush
@endonce
