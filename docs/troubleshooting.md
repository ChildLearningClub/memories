---
description: Solutions to common problems
---

# Troubleshooting

This page contains solutions to common problems. If you are facing any issues, please refer to this page before opening an issue.

## Performance

!!! tip "Nextcloud AIO"

    If you are using [AIO](https://github.com/nextcloud/all-in-one), these options should be configured by default.

Memories is very fast, but its performance largely depends on how well the Nextcloud instance is tuned.

- Make sure you are running the latest stable version of Nextcloud and Memories.
- Follow the steps in the [server tuning](https://docs.nextcloud.com/server/latest/admin_manual/installation/server_tuning.html) documentation.
- Follow all configuration steps in the [configuration](../config) documentation.

      - Disable video transcoding if your server is not powerful enough.
      - Reduce the maximum size of previews to be generated.

- Make sure you are running HTTPS (very important).
- Enable HTTP/2 or HTTP/3 on your server.
- Enable and configure PHP Opcache and JIT.
- Enable and configure the APCu cache.
- Enable and configure Redis for transactional file locking.
- Enable gzip compression on your HTTP server for static assets (CSS/JS).

## No photos are shown

This means that Memories is unable to find any indexed photos in your Nextcloud instance. Make sure you have followed the [configuration steps](../config). Note that Memories indexes photos in the background, so it may take a while for the photos to show up. Ensure that Nextcloud's cron system is properly configured and running.

## Issues with Docker

Note: Using the community [Nextcloud Docker](https://hub.docker.com/_/nextcloud/) image or [AIO](https://github.com/nextcloud/all-in-one) are the recommended ways of running Memories. If you are using a different image, you may run into issues. Please file any bugs you find on GitHub.

### OCC commands fail

The most common reason for this is a missing interactive TTY. Make sure you run the commands with `-it`:

```bash
docker exec -it my_nc_container php occ memories:index
#           ^^^  <-- this is required
```

If you are using Nextcloud AIO, see [this documentation](https://github.com/nextcloud/all-in-one#how-to-run-occ-commands).

!!! warning "OCCWeb"

    The OCCWeb app is deprecated, and will not work with Memories. You must use the `occ` command line.

### Usage of tmpfs

If you are using `tmpfs` (e.g. for the Recognize app), make sure the temp directory is set to executable. With Docker Compose, your `docker-compose.yml` should look like this:

```yaml
app:
    ...
    tmpfs:
    - /tmp:exec
```

`tpmfs` is automatically configured when using Nextcloud AIO v7.0.0.

!!! tip "Changing the binary temp directory"
    
    Alternatively, you may change the temp directory used for binary files to a different directory that is not mounted as `tmpfs`, and allows the executable bit to be set. Use this option with caution.

    ```bash
    occ config:system:set memories.exiftool.tmp --value /path/to/temp/dir
    ```

## Issues with NixOS

### Background index fails

When using the NixOS modules system for installation the indexer may fail on execution. In case the error is either `perl not found` or `failed to run exiftool: ...` it might be that the created `nextcloud-cron` services does not have access to a perl interpreter.

In that case adding perl to the path of the `nextcloud-cron` service might solve the issue.
It can be archived by adding the following snippet to the `configuration.nix`

```nix
systemd.services.nextcloud-cron = {
  path = [pkgs.perl];
};
```

## Reverse Geocoding (Places)

You need to have a MySQL / MariaDB / Postgres database for reverse geocoding to work. SQLite is not supported.

### Planet DB download fails

If the planet DB download does not complete via the admin interface, you need to use the OCC command line, or increase the connection timeout values for your PHP/HTTP servers.

```bash
occ memories:places-setup
```

### Error: Incorrect string value

If you get this error (or an `Incorrect datetime value` error), it is likely that your database is not using the `utf8mb4` character set. Since the reverse geocoding database contains characters in various languages, it is necessary to use `utf8mb4` to store them. To fix this, you need to convert your database to use `utf8mb4`.

You can also try changing `/etc/myt.cnf` in your MySQL/MariaDB server to use `utf8mb4` by default:

```ini
init_connect='SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
```

Restart your database server after making this change.

### General error: 2006 MySQL server has gone away

You may encounter this error where MySQL crashes during planet DB insertion. In this case, use a smaller transaction size for insertion.

```bash
occ memories:places-setup --transaction-size=10
```

## Transcoding

Memories transcodes videos on the fly per-user. This saves space, but requires reasonably good hardware, preferably with hardware acceleration. Check the troubleshooting section [here](/hw-transcoding/#troubleshooting).

## Reset

If you want to completely reset Memories (e.g. for database trouble), uninstall it from the app store, then run the following SQL on your database to clean up any data.
Note that this can have unintended consequences such as some files appearing as duplicates in the mobile app when you reinstall Memories.

```sql
DROP TABLE IF EXISTS oc_memories;
DROP TABLE IF EXISTS oc_memories_livephoto;
DROP TABLE IF EXISTS oc_memories_mapclusters;
DROP TABLE IF EXISTS oc_memories_places;
DROP TABLE IF EXISTS oc_memories_planet;
DROP TABLE IF EXISTS memories_planet_geometry;
DROP INDEX IF EXISTS memories_parent_mimetype ON oc_filecache; /* MySQL */
DELETE FROM oc_migrations WHERE app='memories';
```

On Postgres, the syntax for dropping the index is:

```sql
DROP INDEX IF EXISTS memories_parent_mimetype;
```

!!! warning "Reinstallation"

    The reset will clean up all data associated with Memories. While this is safe and will not delete your files, it can sometimes have unintended side effects, such as some files appearing as duplicates in the mobile apps when you reinstall. Try running `occ memories:index --force` before attempting a reset.

### Instruction set change

If you move from x86 to ARM or vice versa, you need to reset the paths to the architecture specific binaries.

```bash
occ config:system:delete memories.exiftool
occ config:system:delete memories.vod.path
occ config:system:delete memories.vod.ffmpeg
occ config:system:delete memories.vod.ffprobe
occ maintenance:repair
```
