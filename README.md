# RBAC Suite — Laravel Advanced Permission & Role Manager

> Role-based Access Control (RBAC) + Advanced Permissions for Laravel — Free core + optional Pro features (multi-tenant, geo / time restrictions, permission inheritance, caching, import/export).

## 🔎 What is RBAC Suite?

RBAC Suite is a Laravel package that provides a flexible and powerful permission & role management system.  
- Core FREE version offers basic roles, permissions, user-role assignments, groups, permission-role mapping.  
- PRO version (paid / licensed) extends with advanced features like multi-tenant support, time-based permissions, geo / IP restrictions, permission inheritance, caching for performance, import/export, and more.  

It is designed to be easy to integrate for simple projects, yet powerful enough for enterprise-level permission requirements.

## 🚀 Features

### ✅ Free (core) features
- Role creation & management  
- Permission creation & assignment  
- User ↔ Role many-to-many relation  
- Permission ↔ Role many-to-many relation  
- Groups (permission groups) support  
- Basic caching (roles/permissions)  
- Configurable table/column names  

### 💎 PRO (advanced) features
- Permission inheritance (role hierarchy)  
- Time-based permissions (start / end time)  
- Geo / IP restrictions for permissions  
- Multi-tenancy support (tenant-specific permissions)  
- Smart caching mechanism for permission checks  
- Import / Export of permissions configuration (JSON/CSV)  
- Optional license-based distribution for private projects  

## 📦 Installation

Use Composer to install the free version:

```bash
composer require hinditutorpoint/rbac-suite