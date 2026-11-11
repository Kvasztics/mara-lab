"""Extract Markdown from a local document uploaded to Mara."""
import os
import resource
import sys
from zipfile import ZipFile


def main():
    path, extension, text_limit = sys.argv[1:]
    resource.setrlimit(resource.RLIMIT_AS, (1024 ** 3, 1024 ** 3))
    resource.setrlimit(resource.RLIMIT_CPU, (15, 18))

    os.environ["OPENBLAS_NUM_THREADS"] = "1"
    os.environ["OMP_NUM_THREADS"] = "1"
    try:
        from markitdown import MarkItDown, StreamInfo
    except ImportError:
        return 4

    with open(path, "rb") as source:
        if extension == "pdf":
            if b"%PDF-" not in source.read(1024):
                return 2
            source.seek(0)
        else:
            expected = {"docx": "word/document.xml", "pptx": "ppt/presentation.xml",
                        "xlsx": "xl/workbook.xml"}[extension]
            with ZipFile(source) as package:
                members = package.infolist()
                if (len(members) > 2048 or sum(member.file_size for member in members) > 64 * 1024 ** 2
                        or expected not in package.namelist()):
                    return 2
            source.seek(0)

        result = MarkItDown(enable_plugins=False).convert_stream(
            source, stream_info=StreamInfo(extension="." + extension)
        )

    output = result.markdown.replace("\f", "\n").encode("utf-8")
    if len(output) > int(text_limit):
        return 3
    sys.stdout.buffer.write(output)
    return 0


if __name__ == "__main__":
    sys.exit(main())
