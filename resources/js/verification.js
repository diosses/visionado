/**
 * Script de Verificación del Sistema Modular
 * Ejecutar en consola del navegador para verificar que todos los módulos funcionen
 */

class SystemVerifier {
    constructor() {
        this.results = [];
        this.passed = 0;
        this.failed = 0;
    }

    // Ejecutar todas las verificaciones
    async runAll() {
        console.log('🧪 Iniciando verificación del sistema modular...\n');
        
        this.verifyModuleExistence();
        this.verifyStateManager();
        this.verifyModalManager();
        this.verifyHelpers();
        this.verifyLegacyCompatibility();
        this.verifyDOM();
        await this.verifyFunctionality();
        
        this.showResults();
    }

    // Verificar que todos los módulos existan
    verifyModuleExistence() {
        console.log('📦 Verificando existencia de módulos...');
        
        const modules = {
            'StateManager': window.StateManager,
            'ModalManager': window.ModalManager,
            'helpers': window.helpers,
            'AppModules': window.AppModules,
            'BulkSelection': window.BulkSelection
        };
        
        Object.entries(modules).forEach(([name, module]) => {
            this.test(`Module ${name} exists`, !!module);
        });
    }

    // Verificar StateManager
    verifyStateManager() {
        console.log('🗄️ Verificando StateManager...');
        
        if (window.StateManager) {
            try {
                // Test set/get
                StateManager.set('test_key', 'test_value');
                const value = StateManager.get('test_key');
                this.test('StateManager set/get works', value === 'test_value');
                
                // Test eventos
                let eventReceived = false;
                const handler = () => { eventReceived = true; };
                document.addEventListener('state:changed', handler);
                StateManager.set('test_event', 'value');
                setTimeout(() => {
                    this.test('StateManager events work', eventReceived);
                    document.removeEventListener('state:changed', handler);
                }, 10);
                
                // Test clear
                StateManager.clear('test_key');
                this.test('StateManager clear works', StateManager.get('test_key') === null);
                
            } catch (error) {
                this.test('StateManager functionality', false, error.message);
            }
        } else {
            this.test('StateManager available', false);
        }
    }

    // Verificar ModalManager
    verifyModalManager() {
        console.log('🪟 Verificando ModalManager...');
        
        if (window.ModalManager) {
            try {
                // Verificar métodos
                this.test('ModalManager.open exists', typeof ModalManager.open === 'function');
                this.test('ModalManager.close exists', typeof ModalManager.close === 'function');
                this.test('ModalManager.isOpen exists', typeof ModalManager.isOpen === 'function');
                
                // Test con modal ficticio
                const testModal = document.createElement('div');
                testModal.id = 'test-modal';
                testModal.className = 'hidden';
                document.body.appendChild(testModal);
                
                ModalManager.open('test-modal');
                this.test('ModalManager can open modal', !testModal.classList.contains('hidden'));
                
                ModalManager.close('test-modal');
                this.test('ModalManager can close modal', testModal.classList.contains('hidden'));
                
                // Cleanup
                document.body.removeChild(testModal);
                
            } catch (error) {
                this.test('ModalManager functionality', false, error.message);
            }
        } else {
            this.test('ModalManager available', false);
        }
    }

    // Verificar helpers
    verifyHelpers() {
        console.log('🛠️ Verificando helpers...');
        
        if (window.helpers) {
            const expectedFunctions = [
                'showToast',
                'showLoading',
                'hideLoading',
                'formatDate',
                'debounce',
                'throttle',
                'generateUUID',
                'sanitizeHtml',
                'validateEmail',
                'deepClone',
                'serialize',
                'deserialize'
            ];
            
            expectedFunctions.forEach(funcName => {
                this.test(`helpers.${funcName} exists`, typeof helpers[funcName] === 'function');
            });
            
            // Test específicos
            try {
                const uuid = helpers.generateUUID();
                this.test('generateUUID returns valid UUID', /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(uuid));
                
                const email = 'test@example.com';
                this.test('validateEmail works', helpers.validateEmail(email) === true);
                
                const invalidEmail = 'invalid-email';
                this.test('validateEmail rejects invalid', helpers.validateEmail(invalidEmail) === false);
                
            } catch (error) {
                this.test('helpers functionality', false, error.message);
            }
        } else {
            this.test('helpers available', false);
        }
    }

    // Verificar compatibilidad legacy
    verifyLegacyCompatibility() {
        console.log('🔄 Verificando compatibilidad legacy...');
        
        const legacyFunctions = {
            'openModalObra': window.openModalObra,
            'openModalIdentificarObra': window.openModalIdentificarObra,
            'openSeriesWizard': window.openSeriesWizard,
            'initBulkSelection': window.initBulkSelection,
            'showToast': window.showToast,
            'showLoading': window.showLoading,
            'hideLoading': window.hideLoading
        };
        
        Object.entries(legacyFunctions).forEach(([name, func]) => {
            this.test(`Legacy function ${name} exists`, typeof func === 'function');
        });
    }

    // Verificar elementos DOM necesarios
    verifyDOM() {
        console.log('🎯 Verificando elementos DOM...');
        
        // Meta tag CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        this.test('CSRF token meta tag exists', !!csrfToken);
        
        // Verificar modales principales
        const modals = [
            'modal-identificar-obra',
            'modal-create-obra',
            'modal-series-wizard'
        ];
        
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            this.test(`Modal ${modalId} exists in DOM`, !!modal);
        });
        
        // Verificar contenedores de bulk selection
        const bulkContainers = document.querySelectorAll('[data-bulk-actions]');
        this.test('Bulk action containers exist', bulkContainers.length > 0);
    }

    // Verificar funcionalidad real
    async verifyFunctionality() {
        console.log('⚡ Verificando funcionalidad...');
        
        // Test showToast si está disponible
        if (window.showToast) {
            try {
                // Test no debe lanzar error
                showToast('Test toast', 'info');
                this.test('showToast executes without error', true);
            } catch (error) {
                this.test('showToast executes without error', false, error.message);
            }
        }
        
        // Test BulkSelection si está disponible
        if (window.BulkSelection) {
            try {
                const selection = BulkSelection.getSelection();
                this.test('BulkSelection.getSelection works', typeof selection === 'object');
            } catch (error) {
                this.test('BulkSelection functionality', false, error.message);
            }
        }
        
        // Test ModalManager con modal real
        const realModal = document.getElementById('modal-identificar-obra');
        if (realModal && window.ModalManager) {
            try {
                const wasHidden = realModal.classList.contains('hidden');
                ModalManager.open('modal-identificar-obra');
                const isOpen = !realModal.classList.contains('hidden');
                ModalManager.close('modal-identificar-obra');
                const isClosed = realModal.classList.contains('hidden');
                
                this.test('Real modal can be opened and closed', wasHidden && isOpen && isClosed);
            } catch (error) {
                this.test('Real modal functionality', false, error.message);
            }
        }
    }

    // Helper para registrar tests
    test(description, condition, error = null) {
        const status = condition ? '✅' : '❌';
        const message = `${status} ${description}`;
        
        if (condition) {
            this.passed++;
        } else {
            this.failed++;
            if (error) {
                console.error(`   Error: ${error}`);
            }
        }
        
        this.results.push({ description, passed: condition, error });
        console.log(message);
    }

    // Mostrar resultados finales
    showResults() {
        console.log('\n📊 Resultados de la verificación:');
        console.log(`✅ Pasados: ${this.passed}`);
        console.log(`❌ Fallidos: ${this.failed}`);
        console.log(`📊 Total: ${this.passed + this.failed}`);
        
        const successRate = (this.passed / (this.passed + this.failed)) * 100;
        console.log(`🎯 Tasa de éxito: ${successRate.toFixed(1)}%`);
        
        if (this.failed > 0) {
            console.log('\n🚨 Tests fallidos:');
            this.results
                .filter(r => !r.passed)
                .forEach(r => {
                    console.log(`   - ${r.description}${r.error ? `: ${r.error}` : ''}`);
                });
        }
        
        console.log('\n🎉 Verificación completada!');
        
        if (successRate >= 90) {
            console.log('🟢 Sistema en excelente estado para producción');
        } else if (successRate >= 75) {
            console.log('🟡 Sistema funcional, pero revisa los tests fallidos');
        } else {
            console.log('🔴 Sistema necesita atención antes de migrar a producción');
        }
    }
}

// Función para ejecutar la verificación
async function verifyModularSystem() {
    const verifier = new SystemVerifier();
    await verifier.runAll();
    return verifier;
}

// Función de conveniencia para verificación rápida
function quickCheck() {
    console.log('🚀 Verificación rápida del sistema modular:\n');
    
    const checks = {
        'StateManager': !!window.StateManager,
        'ModalManager': !!window.ModalManager,
        'helpers': !!window.helpers,
        'Legacy functions': !!(window.openModalObra && window.showToast),
        'Modules': !!window.AppModules,
        'BulkSelection': !!window.BulkSelection
    };
    
    Object.entries(checks).forEach(([name, status]) => {
        const icon = status ? '✅' : '❌';
        console.log(`${icon} ${name}`);
    });
    
    const allGood = Object.values(checks).every(Boolean);
    console.log(`\n${allGood ? '🎉' : '⚠️'} Estado general: ${allGood ? 'LISTO' : 'NECESITA ATENCIÓN'}`);
    
    if (!allGood) {
        console.log('\n💡 Ejecuta verifyModularSystem() para diagnóstico completo');
    }
}

// Exponer funciones globalmente
window.verifyModularSystem = verifyModularSystem;
window.quickCheck = quickCheck;

// Auto-ejecutar verificación rápida si estamos en modo debug
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('🔧 Sistema modular detectado en localhost');
    console.log('💡 Ejecuta quickCheck() o verifyModularSystem() para verificar el estado');
}