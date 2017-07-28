var metadataFileListPlugin = {
    attach: function(fileList) {
      if (fileList.id === 'trashbin' || fileList.id === 'files.public') {
        return;
      }

      fileList.registerTabView(new OCA.Metadata.MetadataTabView());
    }
};
OC.Plugins.register('OCA.Files.FileList', metadataFileListPlugin);
