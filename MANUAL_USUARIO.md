# Manual de Usuario - SISRH
## Sistema Integral de Salarios y Recursos Humanos

---

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Acceso al Sistema](#acceso-al-sistema)
3. [Dashboard Principal](#dashboard-principal)
4. [Gestión de Empleados](#gestión-de-empleados)
5. [Gestión de Contratos](#gestión-de-contratos)
6. [Gestión de Nóminas](#gestión-de-nóminas)
7. [Reportes](#reportes)
8. [Administración del Sistema](#administración-del-sistema)
9. [Preguntas Frecuentes](#preguntas-frecuentes)
10. [Soporte Técnico](#soporte-técnico)

---

## Introducción

El Sistema Integral de Salarios y Recursos Humanos (SISRH) es una aplicación web diseñada para gestionar de manera eficiente todos los aspectos relacionados con la administración de personal, nóminas y contratos laborales de su empresa.

### Características Principales

- ✅ Gestión completa de empleados
- ✅ Control de contratos laborales
- ✅ Generación automática de nóminas
- ✅ Reportes detallados
- ✅ Sistema de roles y permisos
- ✅ Interfaz moderna y fácil de usar
- ✅ Seguridad avanzada

---

## Acceso al Sistema

### Requisitos del Sistema

- Navegador web moderno (Chrome, Firefox, Safari, Edge)
- Conexión a internet
- Credenciales de acceso válidas

### Inicio de Sesión

1. **Acceder a la URL del sistema**: Abra su navegador web e ingrese la dirección proporcionada por su administrador
2. **Ingresar credenciales**:
   - **Usuario**: Ingrese su nombre de usuario
   - **Contraseña**: Ingrese su contraseña
3. **Hacer clic en "Iniciar Sesión"**

### Credenciales por Defecto

- **Usuario**: `admin`
- **Contraseña**: `admin123`

> ⚠️ **Importante**: Cambie la contraseña por defecto inmediatamente después del primer acceso.

### Recuperación de Contraseña

Si olvida su contraseña, contacte al administrador del sistema para que le genere una nueva.

---

## Dashboard Principal

Al iniciar sesión, será dirigido al dashboard principal que muestra:

### Estadísticas Generales

- **Empleados Activos**: Número total de empleados activos en el sistema
- **Nóminas del Mes**: Cantidad de nóminas generadas en el mes actual
- **Contratos Activos**: Número de contratos laborales vigentes

### Módulos Disponibles

El dashboard presenta accesos rápidos a los principales módulos:

1. **👥 Gestión de Empleados**
2. **💼 Gestión de Contratos**
3. **💰 Gestión de Nóminas**
4. **📊 Reportes**
5. **⚙️ Administración** (solo para administradores)

### Navegación

- **Barra superior**: Muestra información del usuario actual y opción de cerrar sesión
- **Menú principal**: Acceso directo a todas las funcionalidades
- **Breadcrumbs**: Navegación contextual para saber dónde se encuentra

---

## Gestión de Empleados

### Acceder al Módulo

1. Desde el dashboard, haga clic en **"👥 Gestión de Empleados"**
2. O navegue directamente a `/empleados/gestionar.php`

### Funcionalidades Disponibles

#### Ver Lista de Empleados

- **Filtros disponibles**:
  - Por categoría
  - Por cargo
  - Por estado (activo/inactivo)
  - Búsqueda por nombre o cédula

- **Información mostrada**:
  - Nombre completo
  - Cédula de identidad
  - Cargo actual
  - Categoría
  - Salario base
  - Fecha de ingreso
  - Estado

#### Agregar Nuevo Empleado

1. Haga clic en **"➕ Nuevo Empleado"**
2. Complete el formulario con la siguiente información:

   **Datos Personales**:
   - Cédula de identidad (obligatorio)
   - Nombres (obligatorio)
   - Apellidos (obligatorio)
   - Fecha de nacimiento
   - Teléfono
   - Email
   - Dirección

   **Datos Laborales**:
   - Fecha de ingreso (obligatorio)
   - Categoría (obligatorio)
   - Cargo (obligatorio)
   - Salario base (obligatorio)

3. Haga clic en **"Guardar"**

#### Editar Empleado

1. En la lista de empleados, haga clic en **"✏️ Editar"** junto al empleado deseado
2. Modifique los campos necesarios
3. Haga clic en **"Actualizar"**

#### Ver Detalles del Empleado

1. Haga clic en **"👁️ Ver"** junto al empleado
2. Se mostrará información completa incluyendo:
   - Datos personales
   - Historial laboral
   - Contratos asociados
   - Nóminas generadas

---

## Gestión de Contratos

### Acceder al Módulo

1. Desde el dashboard, haga clic en **"💼 Gestión de Contratos"**
2. O navegue directamente a `/contratos/gestionar.php`

### Funcionalidades Disponibles

#### Ver Lista de Contratos

- **Filtros disponibles**:
  - Por tipo de contrato
  - Por estado (activo/inactivo)
  - Por empleado
  - Por fecha de inicio/fin

- **Información mostrada**:
  - Empleado
  - Tipo de contrato
  - Fecha de inicio
  - Fecha de fin
  - Salario
  - Estado

#### Crear Nuevo Contrato

1. Haga clic en **"➕ Nuevo Contrato"**
2. Complete el formulario:

   - **Empleado**: Seleccione de la lista desplegable
   - **Tipo de contrato**: Indefinido, Plazo Fijo, Pasantía
   - **Fecha de inicio**: Fecha de inicio del contrato
   - **Fecha de fin**: Solo para contratos a plazo fijo
   - **Salario**: Monto del salario acordado
   - **Renovación automática**: Marque si aplica

3. Haga clic en **"Guardar"**

#### Gestionar Contratos Existentes

- **Editar**: Modificar términos del contrato
- **Renovar**: Extender la vigencia del contrato
- **Finalizar**: Terminar el contrato antes de su vencimiento

---

## Gestión de Nóminas

### Acceder al Módulo

1. Desde el dashboard, haga clic en **"💰 Gestión de Nóminas"**
2. O navegue directamente a `/nominas/gestionar.php`

### Funcionalidades Disponibles

#### Ver Nóminas Existentes

- **Filtros disponibles**:
  - Por período (mes/año)
  - Por empleado
  - Por estado (pendiente/pagado/anulado)

- **Información mostrada**:
  - Empleado
  - Período
  - Salario bruto
  - Deducciones
  - Salario neto
  - Estado
  - Fecha de pago

#### Generar Nueva Nómina

1. Haga clic en **"➕ Generar Nómina"**
2. Seleccione los parámetros:

   - **Período**: Mes y año de la nómina
   - **Empleados**: Seleccione empleados específicos o todos
   - **Conceptos adicionales**: Bonificaciones o deducciones especiales

3. El sistema calculará automáticamente:
   - Salario bruto
   - Aportes al IPS (personal y patronal)
   - Deducciones
   - Salario neto

4. Revise los cálculos y haga clic en **"Generar"**

#### Ver Detalle de Nómina

1. Haga clic en **"👁️ Ver"** junto a la nómina deseada
2. Se mostrará:
   - Desglose completo de conceptos
   - Cálculos detallados
   - Opción de imprimir recibo de pago

#### Procesar Pagos

1. Seleccione las nóminas a pagar
2. Haga clic en **"Marcar como Pagado"**
3. Ingrese la fecha de pago
4. Confirme la operación

---

## Reportes

### Acceder al Módulo

1. Desde el dashboard, haga clic en **"📊 Reportes"**
2. O navegue directamente a `/reportes/`

### Tipos de Reportes Disponibles

#### Reporte de Nóminas

- **Ubicación**: `/reportes/nomina.php`
- **Descripción**: Reporte detallado de nóminas por período
- **Filtros**:
  - Rango de fechas
  - Empleado específico
  - Departamento
- **Formatos**: PDF, Excel

#### Reporte de Aportes IPS

- **Ubicación**: `/reportes/ips.php`
- **Descripción**: Cálculo de aportes al Instituto de Previsión Social
- **Incluye**:
  - Aportes personales
  - Aportes patronales
  - Totales por período
- **Formatos**: PDF, Excel

### Generar Reportes

1. Seleccione el tipo de reporte deseado
2. Configure los filtros necesarios
3. Elija el formato de salida
4. Haga clic en **"Generar Reporte"**
5. El archivo se descargará automáticamente

---

## Administración del Sistema

> ⚠️ **Nota**: Esta sección solo está disponible para usuarios con rol de **Administrador**.

### Gestión de Usuarios

#### Acceder al Módulo

1. Desde el dashboard, haga clic en **"⚙️ Administración"**
2. Seleccione **"Gestión de Usuarios"**
3. O navegue directamente a `/admin/usuarios.php`

#### Funcionalidades

**Crear Nuevo Usuario**:
1. Haga clic en **"➕ Nuevo Usuario"**
2. Complete el formulario:
   - Nombre de usuario (único)
   - Email (único)
   - Contraseña (mínimo 6 caracteres)
   - Rol (admin, rrhh, contabilidad, empleado)
   - Estado (activo/inactivo)
3. Haga clic en **"Crear"**

**Editar Usuario**:
1. Haga clic en **"✏️ Editar"** junto al usuario
2. Modifique los campos necesarios
3. Para cambiar contraseña, ingrese nueva contraseña
4. Haga clic en **"Actualizar"**

**Eliminar Usuario**:
1. Haga clic en **"🗑️ Eliminar"** junto al usuario
2. Confirme la acción

> ⚠️ **Importante**: No puede eliminar su propio usuario.

### Configuración del Sistema

#### Acceder al Módulo

1. Navegue a `/admin/configuracion.php`

#### Parámetros Configurables

- **Salario mínimo**: Valor del salario mínimo legal
- **Aporte IPS personal**: Porcentaje de aporte personal (por defecto 9%)
- **Aporte IPS patronal**: Porcentaje de aporte patronal (por defecto 16.5%)
- **Día de pago**: Día del mes para pago de salarios
- **Datos de la empresa**: Nombre y RUC

#### Modificar Configuración

1. Edite los valores deseados
2. Haga clic en **"Guardar Cambios"**
3. Los cambios se aplicarán inmediatamente

---

## Roles y Permisos

### Tipos de Usuarios

#### Administrador
- ✅ Acceso completo a todas las funcionalidades
- ✅ Gestión de usuarios
- ✅ Configuración del sistema
- ✅ Todos los reportes

#### Recursos Humanos (RRHH)
- ✅ Gestión de empleados
- ✅ Gestión de contratos
- ✅ Generación de nóminas
- ✅ Reportes de RRHH
- ❌ Gestión de usuarios
- ❌ Configuración del sistema

#### Contabilidad
- ✅ Gestión de nóminas
- ✅ Reportes financieros
- ✅ Reportes de IPS
- ❌ Gestión de empleados
- ❌ Gestión de contratos
- ❌ Gestión de usuarios

#### Empleado
- ✅ Ver sus propios datos
- ✅ Ver sus nóminas
- ✅ Descargar recibos de pago
- ❌ Acceso a datos de otros empleados
- ❌ Funciones administrativas

---

## Preguntas Frecuentes

### ❓ ¿Cómo cambio mi contraseña?

1. Haga clic en su nombre de usuario en la barra superior
2. Seleccione "Cambiar Contraseña"
3. Ingrese su contraseña actual y la nueva contraseña
4. Confirme los cambios

### ❓ ¿Qué hago si olvido mi contraseña?

Contacte al administrador del sistema para que le genere una nueva contraseña temporal.

### ❓ ¿Cómo genero el reporte mensual de nóminas?

1. Vaya al módulo de Reportes
2. Seleccione "Reporte de Nóminas"
3. Configure el mes y año deseado
4. Genere el reporte en formato PDF o Excel

### ❓ ¿Puedo modificar una nómina ya generada?

Solo se pueden modificar nóminas en estado "Pendiente". Las nóminas pagadas no pueden modificarse por seguridad.

### ❓ ¿Cómo agrego un nuevo tipo de contrato?

Contacte al administrador del sistema para agregar nuevos tipos de contrato en la configuración.

### ❓ ¿El sistema calcula automáticamente los aportes al IPS?

Sí, el sistema calcula automáticamente los aportes personal (9%) y patronal (16.5%) según la configuración actual.

---

## Soporte Técnico

### Información de Contacto

- **Email de soporte**: soporte@sisrh.com
- **Teléfono**: +595 21 123-4567
- **Horario de atención**: Lunes a Viernes, 8:00 AM - 6:00 PM

### Antes de Contactar Soporte

1. **Verifique su conexión a internet**
2. **Intente cerrar y abrir sesión nuevamente**
3. **Verifique que esté usando un navegador compatible**
4. **Anote el mensaje de error exacto (si aplica)**

### Información a Proporcionar

Cuando contacte soporte, incluya:

- Su nombre de usuario
- Descripción detallada del problema
- Pasos que realizó antes del error
- Mensaje de error (si aplica)
- Navegador y versión que está usando
- Captura de pantalla (si es relevante)

---

## Actualizaciones del Sistema

### Historial de Versiones

#### Versión 1.0.0 (Actual)
- ✅ Gestión básica de empleados
- ✅ Gestión de contratos
- ✅ Generación de nóminas
- ✅ Reportes básicos
- ✅ Sistema de usuarios y roles

### Próximas Funcionalidades

- 📅 Gestión de vacaciones
- 📅 Control de asistencia
- 📅 Evaluaciones de desempeño
- 📅 Módulo de capacitaciones
- 📅 Integración con sistemas externos

---

## Términos y Condiciones

### Uso del Sistema

- El sistema debe usarse únicamente para fines laborales autorizados
- Cada usuario es responsable de mantener la confidencialidad de sus credenciales
- Está prohibido compartir cuentas de usuario
- Los datos del sistema son confidenciales y no deben ser divulgados

### Seguridad

- Use contraseñas seguras (mínimo 8 caracteres, combinando letras, números y símbolos)
- Cierre sesión al terminar de usar el sistema
- Reporte inmediatamente cualquier actividad sospechosa
- No acceda al sistema desde computadoras públicas o no seguras

---

**© 2024 SISRH - Sistema Integral de Salarios y Recursos Humanos**

*Este manual está sujeto a actualizaciones. Consulte regularmente la versión más reciente.*