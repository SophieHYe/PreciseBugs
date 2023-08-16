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

    private PairDialog pair_dialog;

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

    public async string request_pin_code (ObjectPath device) throws Error, BluezError {
        throw new BluezError.REJECTED ("Pairing method not supported");
    }

    // Called to display a pin code on-screen that needs to be entered on the other device. Can return
    // instantly
    public async void display_pin_code (ObjectPath device, string pincode) throws Error, BluezError {
        pair_dialog = new PairDialog.display_pin_code (device, pincode, main_window);
        pair_dialog.present ();
    }

    public async uint32 request_passkey (ObjectPath device) throws Error, BluezError {
        throw new BluezError.REJECTED ("Pairing method not supported");
    }

    // Called to display a passkey on-screen that needs to be entered on the other device. Can return
    // instantly
    public async void display_passkey (ObjectPath device, uint32 passkey, uint16 entered) throws Error {
        pair_dialog = new PairDialog.display_passkey (device, passkey, entered, main_window);
        pair_dialog.present ();
    }

    // Called to request confirmation from the user that they want to pair with the given device and that
    // the passkey matches. **MUST** throw BluezError if pairing is to be rejected. This is handled in
    // `check_pairing_response`. If the method returns without an error, pairing is authorized
    public async void request_confirmation (ObjectPath device, uint32 passkey) throws Error, BluezError {
        pair_dialog = new PairDialog.request_confirmation (device, passkey, main_window);
        yield check_pairing_response (pair_dialog);
    }

    // Called to request confirmation from the user that they want to pair with the given device
    // **MUST** throw BluezError if pairing is to be rejected. This is handled in `check_pairing_response`
    // If the method returns without an error, pairing is authorized
    public async void request_authorization (ObjectPath device) throws Error, BluezError {
        pair_dialog = new PairDialog.request_authorization (device, main_window);
        yield check_pairing_response (pair_dialog);
    }

    // Called to authorize the use of a specific service (Audio/HID/etc), so we restrict this to paired
    // devices only
    public void authorize_service (ObjectPath device_path, string uuid) throws Error, BluezError {
        var device = get_device_from_object_path (device_path);

        bool paired = device.paired;
        bool trusted = device.trusted;

        // Shouldn't really happen as trusted devices should be automatically authorized, but lets handle it anyway
        if (paired && trusted) {
            // allow
            return;
        }

        // A device that has been paired, but not yet trusted, trust it and allow it to access
        // services
        if (paired && !trusted) {
            device.trusted = true;
            // allow
            return;
        }

        // Reject everything else
        throw new BluezError.REJECTED ("Rejecting service auth, not paired or trusted");
    }

    public void cancel () throws Error {
        if (pair_dialog != null) {
            pair_dialog.cancelled = true;
            pair_dialog.destroy ();
        }
    }

    private async void check_pairing_response (PairDialog dialog) throws BluezError {
        SourceFunc callback = check_pairing_response.callback;
        BluezError? error = null;

        dialog.response.connect ((response) => {
            if (response != Gtk.ResponseType.ACCEPT || dialog.cancelled) {
                if (dialog.cancelled) {
                    error = new BluezError.CANCELED ("Pairing cancelled");
                } else {
                    error = new BluezError.REJECTED ("Pairing rejected");
                }
            }

            Idle.add ((owned)callback);
            dialog.destroy ();
        });

        dialog.present ();

        yield;

        if (error != null) {
            throw error;
        }
    }

    private Device get_device_from_object_path (ObjectPath object_path) throws GLib.Error {
        return Bus.get_proxy_sync<Device> (BusType.SYSTEM, "org.bluez", object_path, DBusProxyFlags.GET_INVALIDATED_PROPERTIES);
    }
}
