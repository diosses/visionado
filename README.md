# Sistema de Visionado de Emisiones

Sistema web para la gestión y visionado de emisiones televisivas desarrollado con Laravel 11. Permite importar, asignar y gestionar el proceso de revisión de contenido audiovisual.

## 🚀 Características

- **Importación de Emisiones**: Carga masiva desde archivos XLSX con validación automática
- **Gestión de Obras**: Catálogo completo con soporte para series y capítulos
- **Asignación Inteligente**: Sugerencias automáticas de obras por similitud de título
- **Sistema de Roles**: Perfiles diferenciados para administradores y visionadores
- **Dashboard Interactivo**: Visualización del estado de visionados pendientes y completados
- **Filtros Avanzados**: Búsqueda por tipo, género, país, año y más

## Screenshots

### Dashboard y login

![Login](screenshots/login.png)
![Dashboard admin 2](screenshots/dashboard_admin1.png)
![Dashboard admin 2](screenshots/dashboard_admin2.png)
![Dashboard admin 3](screenshots/dashboard_admin3.png)
![Dashboard user](screenshots/dashboard_user.png)

### Modales

![Modales 1](screenshots/modals1.png)
![Modales 2](screenshots/modals2.png)
![Modales 3](screenshots/modals3.png)

## 📋 Requisitos

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL/MariaDB
- Extensiones PHP: zip, xml, mbstring, pdo_mysql

## 🛠️ Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/diosses/visionado.git
cd visionado
```

2. **Instalar dependencias**
```bash
composer install
npm install
```

3. **Configurar el entorno**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurar la base de datos**

Edita `.env` con tus credenciales de base de datos:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=visionado
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

5. **Ejecutar migraciones y seeders**
```bash
php artisan migrate --seed
```

6. **Compilar assets**
```bash
npm run build
```

7. **Iniciar el servidor**
```bash
php artisan serve
```

La aplicación estará disponible en `http://localhost:8000`

## 📚 Uso

### Importación de Emisiones (XLSX)

1. Accede al Dashboard como administrador
2. Ve a la pestaña "Material sin asignar"
3. Click en "Importar Emisiones (XLSX)"
4. Sube un archivo con la estructura requerida:
   - **Hoja "Resumen"**: Filas con campo "Visionado" = "PARA VISIONAR"
   - **Hoja "Programas"**: Datos de emisiones que coincidan con el Resumen

### Asignación de Obras

- El material sin asignar se agrupa por "Título Emisión"
- Puedes asignar obras existentes o crear nuevas rápidamente
- Las asignaciones generan visionados en estado pendiente
- Los visionadores pueden iniciar el trabajo desde su dashboard

### Gestión de Obras

- Catálogo completo con filtros por tipo, género, país y año
- Soporte para series con gestión de temporadas y capítulos
- Información detallada de elenco y ficha técnica

## 🗂️ Estructura del Proyecto

```
app/
├── Http/Controllers/    # Controladores de la aplicación
├── Models/             # Modelos Eloquent
├── Imports/            # Clases de importación (Excel)
└── Services/           # Servicios de negocio

database/
├── migrations/         # Migraciones de base de datos
└── seeders/           # Datos iniciales

resources/
├── views/             # Vistas Blade
├── js/                # JavaScript (modules & utils)
└── css/               # Estilos Tailwind
```

## 🔧 Tecnologías

- **Backend**: Laravel 11, PHP 8.2
- **Frontend**: Blade, Alpine.js, Tailwind CSS
- **Base de Datos**: MySQL
- **Importación**: Maatwebsite Excel (PhpSpreadsheet)
- **Build Tools**: Vite

## 📖 Documentación Adicional

Para más detalles sobre el flujo de trabajo y operación de la plataforma, consulta [README_ES.md](README_ES.md)

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 👥 Autor

Desarrollado por [diosses](https://github.com/diosses)
