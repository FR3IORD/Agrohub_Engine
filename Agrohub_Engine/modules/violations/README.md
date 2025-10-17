# Violations Management Module

## 📋 Overview

The Violations Management module is a comprehensive system for tracking, managing, and resolving workplace violations and incidents within the Agrohub ERP ecosystem.

## ✨ Features

- ✅ **Violation Tracking** - Create, view, edit, and delete violations
- ✅ **Photo Attachments** - Upload evidence photos
- ✅ **Sanctions Management** - Apply and track sanctions
- ✅ **Role-based Permissions** - Granular access control
- ✅ **Branch-level Access** - View violations by branch
- ✅ **Real-time Statistics** - Dashboard with live stats
- ✅ **Activity Logging** - Complete audit trail
- ✅ **Export Reports** - PDF and Excel export (coming soon)
- ✅ **Advanced Search** - Filter by status, type, severity
- ✅ **Responsive Design** - Works on all devices

## 📦 Installation

1. Upload module files to `modules/violations/`
2. Import database schema: `db/violations_tables.sql`
3. Register module in main system
4. Grant user access via admin panel

## 🔐 Permissions

- `can_view_all` - View all violations
- `can_view_own` - View own violations
- `can_view_branch` - View branch violations
- `can_create` - Create new violations
- `can_edit_own` - Edit own violations
- `can_edit_all` - Edit any violation
- `can_delete` - Delete violations
- `can_apply_sanctions` - Apply sanctions
- `can_export` - Export reports
- `can_view_analytics` - View analytics

## 📡 API Endpoints

- `GET /api/violations.php?action=list` - Get violations list
- `GET /api/violations.php?action=get&id={id}` - Get single violation
- `POST /api/violations.php?action=create` - Create violation
- `PUT /api/violations.php?action=update&id={id}` - Update violation
- `DELETE /api/violations.php?action=delete&id={id}` - Delete violation
- `GET /api/violations.php?action=stats` - Get statistics
- `GET /api/violations.php?action=types` - Get violation types

## 🎨 Design

The module follows Agrohub ERP design system with:
- Clean, minimal card-based layout
- Purple accent color (#7c3aed)
- Responsive grid system
- Consistent typography and spacing

## 📞 Support

For support, contact: support@agrohub.ge

## 📄 License

Proprietary - Agrohub Team © 2025