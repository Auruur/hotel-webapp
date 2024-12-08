## Hotel Utopia Fullstack Web Application

# Overview

Welcome to the Hotel Utopia project repository! This project is a web application designed for managing hotel reservations, user accounts, and room details. It allows administrators, receptionists, and clients to interact with the system to manage bookings, view room information, and much more.

# Credentials to Access the Application

The application includes two pre-registered admin users that you can use to access the platform:

1. Email: abuela@void.ugr.es Password: abuela
2. Email: tia@void.ugr.es Password: tia

# Database Restoration Files

The following files are included in the backups/ folder to assist with database setup:

- db_prueba.sql: This file contains queries to populate the database with test data based on the project requirements.
- initial_db_setups.sql: This script is used to reset the database, emptying all tables except for the two administrator records in the usuarios table.

# Database Documentation

Entity-Relationship (ER) Model
Below is an outline of the ER model:

A user can create 0 or more reservations, but each reservation is associated with a single user.
A reservation refers to only one room; however, a room can be booked in multiple reservations (for different date ranges).
A room can have 0 or more photographs, and the same photograph can be associated with multiple rooms.
The logs table does not explicitly reference the user who generated the log. However, this information can be found in the log description, which is a string that is editable and not a formal foreign key.
Relational Schema
Reservations Table (reservas)
Fields: id, cliente_id (FK), habitacion_id (FK), num_personas, comentarios, dia_entrada, dia_salida, estado, marca_tiempo.
Key Points: id is the primary key; cliente_id and habitacion_id are foreign keys referencing users and rooms.
Users Table (usuarios)
Fields: id, email (unique), dni (unique), nombre, apellidos, clave, tarjeta, rol.
Key Points: id is the primary key; email and dni are unique identifiers. The password (clave) is hashed.
Rooms Table (habitaciones)
Fields: id, numero (unique), capacidad, precio, descripcion.
Key Points: id is the primary key; numero is a unique identifier for the room.
Photos Table (fotos)
Fields: id, habitacion_id (FK), foto.
Key Points: id is the primary key; habitacion_id is a foreign key referencing rooms. The foto field stores images as longblob.
Logs Table (logs)
Fields: id, fecha, descripcion.
Key Points: id is the primary key; fecha stores the timestamp, and descripcion logs actions.
Technical Details

# Application Structure
The index.php script is the main entry point of the application, minimizing code duplication. All other pages are included within <main> tags based on the URI.
Responsive Design: The app is mobile-friendly, with media queries adjusting the layout for smaller screens. For screens with a width of 550px or less, the left-side menu moves to the top, and the right-side menu moves to the bottom.
Navigation
There are a few pages that cannot be accessed directly from the navigation menu:

Access personal data modification via the profile image in the left sidebar.
Self-registration can be done by clicking the “No account? Register now!” link below the login form.
Database operations can be accessed through the "Database Restoration" text in the footer.
Reservation Process
When a user fills in the reservation form:

find_suitable_room(): This function searches for a room that matches the requested number of people and is available during the specified dates.
If a suitable room is found, create_reservation() creates the reservation with a "Pending" status and saves the timestamp.
If the user confirms the reservation within 30 seconds, the status is changed to "Confirmed" by confirm_reservation(). If the user doesn't confirm within the allowed time, cancel_reservation() deletes the reservation.
The cleanup_expired_reservations() function is called regularly to clean up unconfirmed pending reservations after a certain timeout (configurable in reservas.php and utils.php).
JavaScript Usage
Page Redirection: The application uses JavaScript (via the function page_redirect() in utils.php) for navigation between pages instead of PHP headers.
Photo Slideshow: JavaScript handles the photo gallery for rooms. When users click "View Photos," the toggleFotos() function is triggered, allowing users to scroll through the images with "previous" and "next" buttons.
The app supports multiple image sizes and aspect ratios without distorting the layout.
Optional Items and Customization

The waiting time for reservation confirmation can be adjusted in the reservas.php file (line 70).
The timeout for pending reservation cleanup can be adjusted in the utils.php file (line 493).
