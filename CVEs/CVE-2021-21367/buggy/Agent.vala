/*-
 * Copyright (c) 2018 elementary LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Authored by: Corentin Noël <corentin@elementary.io>
 */

[DBus (name = "org.bluez.Error")]
public errordomain BluezError {
    REJECTED,
    CANCELED
}

[DBus (name = "org.bluez.Agent1")]
public class Bluetooth.Services.Agent : Object {
    private const string PATH = "/org/bluez/agent/elementary";
    Gtk.Window? main_window;

    [DBus (visible=false)]
    public Agent (Gtk.Window? main_window) {
        this.main_window = main_window;
        Bus.own_name (BusType.SYSTEM, "org.bluez.AgentManager1", BusNameOwnerFlags.NONE,
            (connection, name) => {
                try {
                    connection.register_object (PATH, this);
                    ready = true;
                } catch (Error e) {
                    critical (e.message);
                }
            },
            (connection, name) => {},
            (connection, name) => {}
        );
    }

    [DBus (visible=false)]
    public bool ready { get; private set; }

    [DBus (visible=false)]
    public signal void unregistered ();

    [DBus (visible=false)]
    public GLib.ObjectPath get_path () {
        return new GLib.ObjectPath (PATH);
    }

    public void release () throws Error {
        unregistered ();
    }

    public string request_pin_code (ObjectPath device) throws Error, BluezError {
        return "";
    }

    public void display_pin_code (ObjectPath device, string pincode) throws Error, BluezError {
        var pair_dialog = new PairDialog.display_pin_code (device, pincode, main_window);
        pair_dialog.present ();
    }

    public uint32 request_passkey (ObjectPath device) throws Error, BluezError {
        return 0;
    }

    public void display_passkey (ObjectPath device, uint32 passkey, uint16 entered) throws Error {
        var pair_dialog = new PairDialog.display_passkey (device, passkey, entered, main_window);
        pair_dialog.present ();
    }

    public void request_confirmation (ObjectPath device, uint32 passkey) throws Error, BluezError {
        var pair_dialog = new PairDialog.request_confirmation (device, passkey, main_window);
        pair_dialog.present ();
    }

    public void request_authorization (ObjectPath device) throws Error, BluezError {
        var pair_dialog = new PairDialog.request_authorization (device, main_window);
        pair_dialog.present ();
    }

    public void authorize_service (ObjectPath device, string uuid) throws Error, BluezError {
    }

    public void cancel () throws Error {
    }
}
